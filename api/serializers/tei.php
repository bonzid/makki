<?php 

require_once("serializer.php");

class TEISerializer extends Serializer {

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $word_data_quic = [], array $themes = [], array $mimes = []): string {

        $this->word_data = $word_data;

        $title = $this->fetch_translation("title", $langs, $base_url, $protocol)[self::TRANSLATION_TEXT];
        $xml_lang = $langs[0][0];

        $publishers = implode("", array_map(function($item){
            return implode("", array_map(function($pub) {
                return "<publisher>"
                        .$pub["value"].
                    "</publisher>";}, $item));
        }, $this->get_values_of($this->word_data, "publisher")));

        $authors = implode("", array_map(function($item){
            return implode("", array_map(function($auth) {
            return "<persName>
                        <name>".$auth["value"]."</name>
                    </persName>";}, $item));
            }, $this->get_values_of($this->word_data, "creator")));

        $sources =  array_unique(array_map(function($item){return $item['value'];}, array_merge(...$this->get_values_of($this->word_data, "source"))));

        $this->sources = array_merge(...array_map(
                function($item) use ($title){

                    return [$item => "<TEI>
                        <teiHeader>
                            <fileDesc>
                                <titleStmt>
                                    <title>".$item."</title>
                                </titleStmt>
                            </fileDesc>
                        </teiHeader>
                        <text>
                            <body>
                                <div>
                                    <head>$title</head>"];

                }, 
                $sources));

        $corpus_header = "<!-- <!DOCTYPE teiCorpus PUBLIC \"-// TEI P5 //EN\" \"http://www.tei-c.org/release/xml/tei/custom/schema/dtd/tei_all.dtd\"> -->
        <!DOCTYPE teiCorpus SYSTEM \"tei_all.dtd\">
        <teiCorpus xmlns=\"http://www.tei-c.org/ns/1.0\"><teiHeader>
            <fileDesc>
                <titleStmt>
                    <title>$title</title>
                    <author>
                        $authors
                    </author>
                </titleStmt>
                <publicationStmt>
                    $publishers
                </publicationStmt>
            </fileDesc>
        </teiHeader>";


        $this->def_ids = array();
        $this->w_ids = array();
        foreach(array_keys($this->word_data['data']) as $w) {

            $src_to_xml = $this->make_entry($w);

            foreach(array_keys($src_to_xml) as $src) {
                $this->sources[$src] .= $src_to_xml[$src];
            }
        }

        foreach(array_keys($this->word_data_quic['data']) as $w) {

            $src_to_xml = $this->make_entry_quic($w);

            foreach(array_keys($src_to_xml) as $src) {
                $this->sources[$src] .= $src_to_xml[$src];
            }
        }

        foreach(array_keys($this->sources) as $src) {
            $xml .= $this->sources[$src] . "</div></body></text></TEI>";
        }

        $output = new DOMDocument("1.0", "UTF-8");
        $output->preserveWhiteSpace = FALSE;
        $output->loadXML($corpus_header.$xml."</teiCorpus>");
        $output->formatOutput = TRUE;
        return html_entity_decode($output->saveXML());
    }


    function make_entry($w): array {

        $w_id = $this->get_word_id($w);


        $src_to_xml = array();

        foreach(array_keys($this->word_data['data'][$w]) as $def) {
            $id = $this->get_def_id($def);

            if(array_key_exists($id, $this->def_ids)) {
                continue;
            }

            $d = "";

            $this->def_ids[$id] = true;

            foreach($this->word_data['data'][$w][$def]['abstract'] as $abs) {
                $d .= "<def xml:lang=\"".$abs['lang']."\">".$abs['value']."</def>";
            }

            if (isset($this->word_data['data'][$w][$def]['subject'])) {
                foreach($this->word_data['data'][$w][$def]['subject'] as $subj) {
                    $d .= "<usg type=\"dom\" xml:lang=\"".$subj['lang']."\">".$subj['value']."</usg>";
                }
            }

            if (isset($this->word_data['data'][$w][$def]['pron'])) {
                foreach($this->word_data['data'][$w][$def]['pron'] as $pron) {
                    $d .= "<pron xml:lang=\"".$pron['lang']."\">".$pron['value']."</pron>";
                }
            }

            if (isset($this->word_data['data'][$w][$def]['etymo'])) {
                foreach($this->word_data['data'][$w][$def]['etymo'] as $etymo) {
                    $d .= "<etym xml:lang=\"".$etymo['lang']."\">".$etymo['value']."</etym>";
                }
            }
            
            if (isset($this->word_data['data'][$w][$def]['coverage'])) {
                foreach($this->word_data['data'][$w][$def]['coverage'] as $coverage) {
                    $d .= "<usg type=\"geo\" xml:lang=\"".$coverage['lang']."\">".$coverage['value']."</usg>";
                }
            }

            foreach($this->word_data['data'][$w][$def]['syn'] as $syn) {
                $wid = $this->get_word_id($syn['value']);

                if ($wid != $w_id) {
                    if ($wid != false) {
                        $d .= "<xr type=\"syn\"><ptr target=\"#$wid\"/></xr>";
                    }
                    else {
                        $cut = explode("/", $syn['URI']);
                        $wid = end($cut);
                        $url = implode('/', array_slice($cut, 0, -1)) . "/" . $syn['value'];
                        $d .= "<xr type=\"syn\"><ptr target=\"#$wid\" url=\"$url\"/></xr>";
                    }
                        
                    
                }
            }

            $d .= "</sense>";

            $first = true;
            foreach($this->word_data['data'][$w][$def]['source'] as $src) {

                if (array_key_exists($w_id, $this->w_ids)) {
                    $src_to_xml[$src['value']] = "<entry><form><orth>$w</orth></form><ptr target=\"#$w_id\"></ptr>";
                }
                else {
                    $src_to_xml[$src['value']] = "<entry xml:id=\"$w_id\"><form><orth>$w</orth></form>";
                    $this->w_ids[$w_id] = true;
                }

                if ($first && !array_key_exists($id, $this->def_ids)) {
                    $head = "<sense xml:id=\"$id\">";
                    $first = false;
                    $this->def_ids[$id] = true;
                }
                else {
                    $head = "<sense><xr type=\"cf\"><ptr target=\"#$id\"/></xr>";
                }

                $src_to_xml[$src['value']] .= $head . $d;
                
            }

        }

        foreach(array_keys($src_to_xml) as $src) {
            $src_to_xml[$src] .= "</entry>";
        }

        return $src_to_xml;
    }

    function make_entry_quic($w): array {

        $w_id = $this->get_word_id($w);


        $src_to_xml = array();

        foreach(array_keys($this->word_data_quic['data'][$w]) as $def) {
            $id = $this->get_def_id($def);

            if(array_key_exists($id, $this->def_ids)) {
                continue;
            }

            $d = "";

            $this->def_ids[$id] = true;

            foreach($this->word_data_quic['data'][$w][$def]['abstract'] as $abs) {
                $d .= "<def xml:lang=\"".$abs['lang']."\">".$abs['value']."</def>";
            }

            if (isset($this->word_data_quic['data'][$w][$def]['subject'])) {
                foreach($this->word_data_quic['data'][$w][$def]['subject'] as $subj) {
                    $d .= "<usg type=\"dom\" xml:lang=\"".$subj['lang']."\">".$subj['value']."</usg>";
                }
            }

            if (isset($this->word_data_quic['data'][$w][$def]['pron'])) {
                foreach($this->word_data_quic['data'][$w][$def]['pron'] as $pron) {
                    $d .= "<pron xml:lang=\"".$pron['lang']."\">".$pron['value']."</pron>";
                }
            }

            if (isset($this->word_data_quic['data'][$w][$def]['etymo'])) {
                foreach($this->word_data_quic['data'][$w][$def]['etymo'] as $etymo) {
                    $d .= "<etym xml:lang=\"".$etymo['lang']."\">".$etymo['value']."</etym>";
                }
            }
            
            if (isset($this->word_data_quic['data'][$w][$def]['coverage'])) {
                foreach($this->word_data_quic['data'][$w][$def]['coverage'] as $coverage) {
                    $d .= "<usg type=\"geo\" xml:lang=\"".$coverage['lang']."\">".$coverage['value']."</usg>";
                }
            }

            foreach($this->word_data_quic['data'][$w][$def]['syn'] as $syn) {
                $wid = $this->get_word_id($syn['value']);

                if ($wid != $w_id) {
                    if ($wid != false) {
                        $d .= "<xr type=\"syn\"><ptr target=\"#$wid\"/></xr>";
                    }
                    else {
                        $cut = explode("/", $syn['URI']);
                        $wid = end($cut);
                        $url = implode('/', array_slice($cut, 0, -1)) . "/" . $syn['value'];
                        $d .= "<xr type=\"syn\"><ptr target=\"#$wid\" url=\"$url\"/></xr>";
                    }
                        
                    
                }
            }

            $d .= "</sense>";

            $first = true;
            foreach($this->word_data_quic['data'][$w][$def]['source'] as $src) {

                if (array_key_exists($w_id, $this->w_ids)) {
                    $src_to_xml[$src['value']] = "<entry><form><orth>$w</orth></form><ptr target=\"#$w_id\"></ptr>";
                }
                else {
                    $src_to_xml[$src['value']] = "<entry xml:id=\"$w_id\"><form><orth>$w</orth></form>";
                    $this->w_ids[$w_id] = true;
                }

                if ($first && !array_key_exists($id, $this->def_ids)) {
                    $head = "<sense xml:id=\"$id\">";
                    $first = false;
                    $this->def_ids[$id] = true;
                }
                else {
                    $head = "<sense><xr type=\"cf\"><ptr target=\"#$id\"/></xr>";
                }

                $src_to_xml[$src['value']] .= $head . $d;
                
            }

        }

        foreach(array_keys($src_to_xml) as $src) {
            $src_to_xml[$src] .= "</entry>";
        }

        return $src_to_xml;
    }


    function get_values_of(array $ar, string $key, bool $deep = true): array {

        $output = array();

        foreach(array_keys($ar['data']) as $w) {

            foreach(array_keys($ar['data'][$w]) as $def) {
                if (array_key_exists($key, $ar['data'][$w][$def])) {
                    if (!in_array($ar['data'][$w][$def][$key], $output)) {

                        if ($deep) {
                            array_push($output, $ar['data'][$w][$def][$key]); 
                        }
                        else {
                            array_push($output, $ar['data'][$w][$def][$key][0]);
                        }

                    }
                }
            }
        }

        return $output;
    }

    function get_def_id(string $def) {
        $id = explode("/", $def);
        $id = end($id);

        return "sense_" . str_replace("#", "_", $id);
    }

    function get_word_id(string $w) {

        if (array_key_exists($w, $this->word_data['data'])) {
            $w_id = explode("/", array_keys($this->word_data['data'][$w])[0]);
            $w_id = end($w_id);
            $w_id = explode("#", $w_id)[0];

            return "entry_" . $w_id;
        }
        else {
            return false;
        }
        
    }
}

?>