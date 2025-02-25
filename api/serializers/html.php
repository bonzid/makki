<?php 

require_once(__DIR__."/../simple_html_dom/simple_html_dom.php");
require_once(__DIR__."/../jwt.php");
require_once("serializer.php");

class HTMLSerializer extends Serializer {

    function __construct($db) {

        $paths = file_get_contents("config/pages.json");
        $this->pages = array("header" => "html/header.html", "footer" => "html/footer.html");
        parent::__construct($db);

        if ($paths != false) {
            $data = json_decode($paths, true);

            foreach(array_keys($data) as $h) {
                $this->pages[$h] = $data[$h];
            }
        }
        else {
            die("Erreur lors du chargement du fichier pages.json"); #TODO
        }
    }

    //paramètres: 
    // -$page nom de la page à générer
    // -$base_url : l'url de base pour construire les liens
    // -$langs : tableau des langues disponibles
    // -$request : données de la requête HTTP
    // -$protocol : le protocole
    // -$connected : booléen indiquant si l'utilisateur est connecté ou non
    // -$token : jeton de securité
    // -$word_data : données relatives à mots (optionnel)
    // -$themes : themes dispo (optionnel)
    // -$mimes : types MIME pour des fichiers (optionnel) 
    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = [], array $mimes = []): string {

        //vérification de la validité de la page
        if (!array_key_exists($page, $this->pages)) {
            echo "<pre>"; // Pour un formatage lisible dans un navigateur
            echo "La page demandée n'existe pas. Voici le contenu de \$this->pages :\n";
            print_r($this->pages);
            echo "</pre>";
            die(); // Arrête l'exécution du script après affichage
        }

        //chargement des éléments HTML de base
        $header = file_get_html($this->pages["header"]);
        $footer = file_get_html($this->pages["footer"]);
        $output = file_get_html($this->pages[$page]);

        /*echo "<pre>";
        print_r($this->pages);
        echo "</pre>";
        exit;
        */

        //$page = isset($_GET['page']) ? (is_array($_GET['page']) ? $_GET['page'][0] : $_GET['page']) : "accueil";

        //traitement spécifique pour certaines pages


        if ($page == "quiconnait") {
            //var_dump($output);
            $this->make_data_special($word_data, $output, $langs, $base_url, $protocol, $mimes, (isset($request['lang']) ? $request['lang'] : null));
        }
        

        else if ($page == "mot") {
            $this->make_data($word_data, $output, $langs, $base_url, $protocol, $mimes, (isset($request['lang']) ? $request['lang'] : null));
        }
        else if ($page == "recherche") {
            $output->find("#search-form", 0)->action = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."lexique";

            $dl_th = $output->find("datalist.theme", 0);
            foreach($themes as $th) {
                $dl_th->innertext .= "<option value=\"$th\">$th</option>";
            }
        }
        

        //debug
        /*
        else if ($page == "quiconnait") {
            echo "<pre>";
            print_r($word_data);
            echo "</pre>";
            exit;
        }
        */

        
        //modif de l'élément body
        $html = $output->find('html', 0);
        $body = $html->find('body', 0);


        //traitement des msg selon la requête et la langue
        if(isset($request['query']['msg'])) {

            foreach($request['query']['msg'] as $id) {
                $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
                $lang = $ts[self::TRANSLATION_LANG];
                $dir = $ts[self::TRANSLATION_DIR];
                $value = $ts[self::TRANSLATION_TEXT];

                $body->innertext = "<div class=\"msg\"><p lang=\"$lang\" dir=\"$dir\">$value</p><button class=\"msg_btn\">x</button></div>" . $body->innertext;
            }
        }
        
        $bar = $header->find('.barre', 0);

        $lang_list = $bar->find('ul#lang_liste', 0);
        $themes_list = $bar->find('ul#thematiques_liste', 0);
        $body->dir = $langs[0][1];

        //modification des liens dans header et footer
        $accueil_link = $header->find('#accueil', 0);
        $accueil_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "");

        $merci_link=$header->find('#remerciements',0);
        $merci_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."remerciements";

        $quiconnait_link=$header->find('#quiconnait',0);
        $quiconnait_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."quiconnait";

        $contact_link = $footer->find('#contact', 0);
        $contact_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."contact";

        $bibliographie_link = $footer->find('#bibliographie', 0);
        $bibliographie_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."bibliographie";

        $licence_link = $footer->find('#licence', 0);
        $licence_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."licences";

        $recherche_poussee_link = $header->find("#recherche_poussee", 0);
        $recherche_poussee_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."recherche";

        $lexique_entier_link = $header->find("#lexique_entier", 0);
        $lexique_entier_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."lexique";

        //$body->dir = $langs[0][1];
        

        //modification des listes de langue et de thèmes
        $lang_list->innertext = "";
        $sorted_langs = $langs;
        asort($sorted_langs);
        $nb = count($sorted_langs);
        $c = 0;
        foreach($sorted_langs as $l) {
            $c += 1;
            $lang_list->innertext .= "<li><a ". ($c == $nb ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.$l[0]."/". (isset($request['collection']) ? $request['collection'] . "/" : ""). (isset($request['target']) ? $request['target'] . "/" : "") ."\">".$l[0]."</a></li>";
        }

        $themes_list->innertext = "<li><a id=\"valeur_aucune\" class=\"trad\" href=\"".$protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."lexique?subject=none\"></a></li>";
        $sorted_themes = $themes;
        asort($sorted_themes);
        $nb = count($sorted_themes);
        $c = 0;
        foreach($themes as $t) {
            $c += 1;
            $themes_list->innertext .= "<li><a ". ($c == $nb ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."lexique?subject=".$t."\">".$t."</a></li>";
        }


        //gestion de la connexion de l'utilisateur
        if ($connected) {
            $header->find('.login-wrapper', 0)->outertext = "";
            $header->find(".login-wrapper_connecte", 0)->removeAttribute("hidden");
            $header->find("#bouton_admin", 0)->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."administration";
        }
        else {
            $header->find(".login-wrapper_connecte", 0)->outertext = "";

            if (!is_file("secure/public_keys.json")) {
                make_keyset();
            }

            $keys = json_decode(file_get_contents("secure/public_keys.json"), true)["keys"];
            $key = null;

            foreach($keys as $k) {
                if ($k["kid"] == "enc-login") {
                    $key = $k;
                }
            }
            $key = base64_encode(JWT::encode(json_encode($key), "+60 minutes"));
            $login_form = $header->find("form.login-form", 0);
            $login_form->setAttribute("data-key", $key);
        }
        
        //assemblage du HTML final
        $body->innertext = $header->find('header', 0)->outertext . $bar->outertext . $body->innertext . $footer->find('footer', 0)->outertext;
        

        $output = $output->save();
        $output = str_get_html($output);

        if ($token != "") {
            $nonce = "<input type=\"hidden\" id=\"nonce\" name=\"nonce\" value=\"".urlencode($token)."\">";

            foreach($output->find("form") as $form) {
                if ($form->method != "GET")
                    $form->innertext = $nonce . $form->innertext;
            }
        }

        if ($page == "administration") {
            $output->find("html", 0)->setAttribute("data-nonce", urlencode($token));
            $output = $this->make_table_trad($output, $protocol, $base_url);
            $output = $this->make_table_data($output, $protocol, $base_url);
            $output = $this->make_table_contact($output, $protocol, $base_url);
        }
        $output = $output->save();
        $output = str_get_html($output);


        // Images et des liens
        foreach($output->find('img') as $e) {
            $e->src = $protocol.$base_url.$e->src;
        }
        foreach($output->find('.img') as $e){
            $e->src = $protocol.$base_url.$e->src;
        }
        foreach($output->find('link') as $e) {
            // Feuilles de style
            $e->href = $protocol.$base_url.$e->href;
        }
        foreach($header->find('link') as $e) {
            $e->href = $protocol.$base_url.$e->href;
            $output->find('head', 0)->appendChild($e);
        }
        foreach($footer->find('link') as $e) {
            $e->href = $protocol.$base_url.$e->href;
            $output->find('head', 0)->appendChild($e);
        }
        foreach($output->find('script') as $e) {
            // Scripts
            $e->src = $protocol.$base_url.$e->src;
        }
        foreach($output->find('.trad') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->innertext = $ts[self::TRANSLATION_TEXT];
            
        }
        foreach($output->find('.trad_placeholder') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->placeholder = $ts[self::TRANSLATION_TEXT];
        }
        foreach($output->find('.trad_value') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->value = $ts[self::TRANSLATION_TEXT];
        }

        //retour du contenu final
        return $output->innertext;

    }

    function make_table_trad($html, $protocol, $base_url) {

        foreach($html->find('table#table_fichier_trad>tbody') as $t) {

            $rows = $this->db->query("SELECT ?language ?file ?date WHERE { ?in dcterms:source ?file . ?in dcterms:language ?language . ?in dcterms:date ?date } GROUP BY ?file ?language ?date");

            foreach($rows['result']['rows'] as $r) {
                $lang = $r['language'];
                $file = $r['file'];
                $date = $r['date'];
                $t->innertext .= "<tr>
                                    <td>$lang</td>
                                    <td>$file</td>
                                    <td>$date</td>
                                    <td><button class=\"trad delete_trad boutons_fichiers\" data-url=\"".$protocol.$base_url."traductions\" data-lang=\"".$lang."\" data-file=\"".$file."\" data-date=\"".$date."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function make_table_data($html, $protocol, $base_url) {

        foreach($html->find('table#table_fichier_data>tbody') as $t) {

            $rows = $this->db->query("SELECT ?file WHERE { ?in dcterms:source ?file . ?in dcterms:title ?title } GROUP BY ?file");

            foreach($rows['result']['rows'] as $r) {
                $file = $r['file'];
                $t->innertext .= "<tr>
                                    <td>$file</td>
                                    <td><button class=\"delete_data trad boutons_fichiers\" data-url=\"".$protocol.$base_url."lexique\" data-file=\"".$file."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function make_table_contact($html, $protocol, $base_url) {

        foreach($html->find('table#table_contacts>tbody') as $t) {

            $stt = $this->db->pdo->prepare("SELECT * FROM `contact`");
            $stt->execute();

            $resultat = $stt->fetchAll(PDO::FETCH_ASSOC);

            foreach($resultat as $r) {

                $name = $r['name'];
                $email = $r['email'];
                $subject = $r['subject'];
                $message = $r['message'];
                $id = $r["contact_id"];

                $t->innertext .= "<tr>
                                    <td>$name</td>
                                    <td>$subject</td>
                                    <td>$email</td>
                                    <td>$message</td>
                                    <td><button class=\"delete_contact trad boutons_fichiers\" data-file=\"$id\" data-url=\"".$protocol.$base_url."contact\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }


    //Génère une structure HTML en remplissant un modèle avec des données de mots et leurs infos associées 
    //en entrée :
    // $word_data : un tableau contenant des mots et leurs données associées
    //$template : un objet représentant le modèle HTML
    //$langs : un tableau contenant les langues et leurs paramètres (langue principale et direction par ex)
    //$base_url et $protocol : construisent les URLs des liens
    //$mimes : un tableau de types MIME pour l'exportation
    //$req_lang : la langue demandée (peut être null)
    function make_data(array $word_data, $template, array $langs, string $base_url, string $protocol, array $mimes, $req_lang) {
        //préparation du modèle html
        $ex = $template->find(".mots_donnees", 0); //on récupère la section HTML mots_donnees où seront affichées les données des mots
        $page_sys = $template->find(".pagination"); //section paginations
        $ex->innertext = ""; //vide la section précédente pour éviter le cumul de données

        //création d'une fonction pour chaînes de requête (ajout page et page_size)
        $make_query_string = function(array $q, int $p = 1, int $p_s = 1) {
            $q['page'] = $p;
            $q['page_size'] = $p_s;
            return urldecode(preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($q, null, '&')));
        };

        //si word_data vide, la fonction s'arrête
        if (sizeof($word_data) <= 0) {
            return;
        }

        //gestion de la pagination
        $pagination = $word_data['pagination'];
        $word_data  = $word_data['data'];

        if ($pagination["nb_results"] > 0) {
            $r = $template->find("div.resultats", 0);
            $r->innertext .= $pagination["nb_results"];

            //$select = $template->find("#export_data_select", 0);
            
            //foreach($mimes as $m) {
            //    if ($m != "*/*" && $m != "text/html")
            //        $select->innertext .= "<option value=\"$m\">".explode("/", $m)[1]."</option>";
            //}


            //$export_btn = $template->find("#export_data_btn", 0);
            $temp_q = $pagination['query'];
            //$export_btn->setAttribute("data-url", $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?".$make_query_string($temp_q, (isset($temp_q['page']) ? $temp_q['page'][0] : 1), $pagination['page_size']));
            $temp_q['mime'] = $mimes[0];

            
            //$export_btn->href = $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?".$make_query_string($temp_q, isset($temp_q['page']) ? $temp_q['page'][0] : 1, $pagination['page_size']);
            
        }

        //suppression de la pagination si aucun resultat
        else {
            $template->find("div.thematiqueseule", 0)->outertext = "";
            foreach($template->find(".pagination") as $pagin) {
                $pagin->outertext = "";
            }
        }

        //on détermine la page actuelle
        $current_page = isset($pagination['query']['page']) ? (int)$pagination['query']['page'][0] : 1;

        //on génère les numéros de pages affichés
        $expand_numbers = function(int $n, int $k, int $min=1, int $max=10) {

            $n = min(max($n, $min), $max);

            $output = array(
                $n
            );
            
            $last_up = $n;
            $last_down = $n;

            $limit = min($k, $max-$min+1);

            while(sizeof($output) < $limit) {
                if ($last_down-1 >= $min) {
                    $last_down -= 1;
                    array_unshift($output, $last_down);
                }

                if (sizeof($output) < $limit) {
                    if ($last_up+1 <= $max) {
                        $last_up += 1;
                        array_push($output, $last_up);
                    }
                }
            }

            return $output;
        };

        //construction de la pagination
        foreach($page_sys as $el) {


            $el->innertext .= "<a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?".$make_query_string($pagination['query'], 1, $pagination['page_size'])."\">&laquo;</a>";

            foreach($expand_numbers($current_page, 7, 1, $pagination['nb_pages']) as $p) {
                $el->innertext .= "<a ".($p == $current_page ? "class=\"active\"" : "")." href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?".$make_query_string($pagination['query'], $p, $pagination['page_size'])."\">$p</a>";
            }

            $el->innertext .= "<a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?".$make_query_string($pagination['query'], $pagination['nb_pages'], $pagination['page_size'])."\">&raquo;</a>";
        }

        //construction des définitions des mots
        foreach(array_keys($word_data) as $word) { //liste <ol> pour chaque mot avec son identifiant et un lien vers sa page
            $ex->innertext .= "<ol id=\"$word\" class=\"thematiqueseule\"><h3 lang=\"ar\" dir=\"rtl\"><a class=\"mot_titre\" href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique/".$word."\">$word<a></h3>";

            foreach(array_keys($word_data[$word]) as $def_id) {

                $ex->innertext .= "<li class=\"definition\" id=\"$def_id\">"; //ajout d'une liste <li> pour chaque def du mot

                //ajout d'informations pour chaque mot
                //définition principale
                if (array_key_exists("abstract", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["abstract"]) > 0) {
                    foreach($word_data[$word][$def_id]["abstract"] as $abstract) {
                        if ($abstract["lang"] == $langs[0][0]) {
                            $ex->innertext .= "<p class=\"defmot\" lang=\"".$abstract["lang"]."\" dir=\"".$langs[0][1]."\">".htmlentities($abstract["value"])."</p>";
                        }
                    }
                }

                //ajout des thématiques
                if (array_key_exists("subject", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["subject"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"theme\"> Thématiques : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["subject"] as $subject) {
                        if ($subject["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$subject["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?subject=".urlencode($subject["value"])."\">".$subject["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . "</ul>";
                    }
                }

                //ajout des synonymes
                if (array_key_exists("syn", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["syn"]) > 1) {

                    // On met sizeof($word_data[$word][$def_id]["syn"]) > 1 car le mot lui-même est présent dans les synonymes

                    $txt = "<p class=\"trad\" id=\"synonyme\">Synonymes : </p><p lang=\"ar\" dir=\"rtl\"> ";

                    $map = function($syn) use ($word, $protocol, $base_url, $word_data, $req_lang) {

                            if (array_key_exists($syn["value"], $word_data)) {
                                $link = "#".$syn["value"];
                            } else {
                                $link = $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique/".urlencode($syn["value"]);
                            }
                            return "<i><a href=\"".$link."\">".$syn["value"]."</a></i>";
                        };

                    
                    $txt .= implode(" ، ", array_map($map, array_filter($word_data[$word][$def_id]["syn"], function($el) use ($word) {
                                if ($el["value"] != $word)
                                    return true;

                                return false;
                            })
                        )
                    );

                    $ex->innertext .= $txt . " </p>";

                }

                //ajout des origines des mots
                if (array_key_exists("coverage", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["coverage"]) > 0) {
                    $txt = "<p class=\"trad\" id=\"origine\"> Origines : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["coverage"] as $coverage) {
                        #if ($coverage["lang"] == $langs[0][0]) {
                            #$one = true;
                            #$txt .= "<li lang=\"".$coverage["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?coverage=".urlencode($coverage["value"])."\">".$coverage["value"]."</a></li>";
                        #}
                        if (strcasecmp($coverage["lang"], $langs[0][0]) === 0) {
                            $one = true;
                            $txt .= "<li lang=\"".$coverage["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?coverage=".urlencode($coverage["value"])."\">".$coverage["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                //ajout des prononciations
                if (array_key_exists("pron", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["pron"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"pron\"> Prononciations : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["pron"] as $pron) {
                        if ($pron["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$pron["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?pron=".urlencode($pron["value"])."\">".$pron["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                //ajout d'étymologies
                if (array_key_exists("etymo", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["etymo"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"etymo\"> Etymologie : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["etymo"] as $etymo) {
                        if ($etymo["lang"] == $langs[0][0]) {
                            $txt .= "<li lang=\"".$etymo["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."lexique?etymo=".urlencode($etymo["value"])."\">".$etymo["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                //ajout d'exemples si il y en a
                if (array_key_exists("example", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["example"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"exemple\"> Exemples : </p>";
                    $txt .= "<ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["example"] as $example) {
                        if ($example["lang"] == "ar") {
                            $one = true;
                            $txt .= "<li lang=\"".$example["lang"]."\" dir=\"rtl\"><i>".$example["value"]."</i></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                $ex->innertext .= "<br/></li>";
            }

            //fermeture html mots
            $ex->innertext .= "</ol>";
        }

    }

    //Make_data mais pour le template "Connaissez-vous ce mot?"
    function make_data_special(array $word_data, $template, array $langs, string $base_url, string $protocol, array $mimes, $req_lang) {

        $ex = $template->find(".quiconnait_donnees", 0);
        $page_sys = $template->find(".pagination");
        $ex->innertext = ""; //vide le contenu de la classe "quiconnait_donnees" sur HTML

        $make_query_string = function(array $q, int $p = 1, int $p_s = 1) {
            $q['page'] = $p;
            $q['page_size'] = $p_s;
            return urldecode(preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($q, null, '&')));
        };

        if (sizeof($word_data) <= 0) {
            error_log("Aucune donnée trouvée."); // Affichage dans la console/logs PHP
            $ex->innertext = "<p class='error'>Aucune donnée trouvée.</p>"; // Affichage sur la page
            return; 
        }

        $pagination = $word_data['pagination'];
        $word_data  = $word_data['data'];

        if ($pagination["nb_results"] > 0) {
            $r = $template->find("div.resultats", 0);
            $r->innertext .= $pagination["nb_results"];

            //$select = $template->find("#export_data_select", 0);
            
            //foreach($mimes as $m) {
            //    if ($m != "*/*" && $m != "text/html")
            //        $select->innertext .= "<option value=\"$m\">".explode("/", $m)[1]."</option>";
            //}


            //$export_btn = $template->find("#export_data_btn", 0);
            $temp_q = $pagination['query'];
            //$export_btn->setAttribute("data-url", $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?".$make_query_string($temp_q, (isset($temp_q['page']) ? $temp_q['page'][0] : 1), $pagination['page_size']));
            $temp_q['mime'] = $mimes[0];

            
            //$export_btn->href = $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?".$make_query_string($temp_q, isset($temp_q['page']) ? $temp_q['page'][0] : 1, $pagination['page_size']);
            
        }
        else {
            $template->find("div.thematiqueseule", 0)->outertext = "";
            foreach($template->find(".pagination") as $pagin) {
                $pagin->outertext = "";
            }
        }

        $current_page = isset($pagination['query']['page']) ? (int)$pagination['query']['page'][0] : 1;

        $expand_numbers = function(int $n, int $k, int $min=1, int $max=10) {

            $n = min(max($n, $min), $max);

            $output = array(
                $n
            );
            
            $last_up = $n;
            $last_down = $n;

            $limit = min($k, $max-$min+1);

            while(sizeof($output) < $limit) {
                if ($last_down-1 >= $min) {
                    $last_down -= 1;
                    array_unshift($output, $last_down);
                }

                if (sizeof($output) < $limit) {
                    if ($last_up+1 <= $max) {
                        $last_up += 1;
                        array_push($output, $last_up);
                    }
                }
            }

            return $output;
        };

        foreach($page_sys as $el) {


            $el->innertext .= "<a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?".$make_query_string($pagination['query'], 1, $pagination['page_size'])."\">&laquo;</a>";

            foreach($expand_numbers($current_page, 7, 1, $pagination['nb_pages']) as $p) {
                $el->innertext .= "<a ".($p == $current_page ? "class=\"active\"" : "")." href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?".$make_query_string($pagination['query'], $p, $pagination['page_size'])."\">$p</a>";
            }

            $el->innertext .= "<a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?".$make_query_string($pagination['query'], $pagination['nb_pages'], $pagination['page_size'])."\">&raquo;</a>";
        }


        //AFFICHAGE DES MOTS ET DEFINITIONS
        foreach(array_keys($word_data) as $word) {
            $ex->innertext .= "<ol id=\"$word\" class=\"thematiqueseule\"><h3 lang=\"ar\" dir=\"rtl\"><a class=\"mot_titre\" href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait/".$word."\">$word<a></h3>";

            foreach(array_keys($word_data[$word]) as $def_id) { //AFFICHAGE DES DEFINITIONS

                $ex->innertext .= "<li class=\"definition\" id=\"$def_id\">";

                //si un résumé existe, on l'affiche dans <p class="defmot">
                if (array_key_exists("abstract", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["abstract"]) > 0) {
                    foreach($word_data[$word][$def_id]["abstract"] as $abstract) {
                        if ($abstract["lang"] == $langs[0][0]) {
                            $ex->innertext .= "<p class=\"defmot\" lang=\"".$abstract["lang"]."\" dir=\"".$langs[0][1]."\">".htmlentities($abstract["value"])."</p>";
                        }
                    }
                }


                //si une thématique existe, elle est affichée sous forme de lien
                if (array_key_exists("subject", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["subject"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"theme\"> Thématiques : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["subject"] as $subject) {
                        if ($subject["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$subject["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?subject=".urlencode($subject["value"])."\">".$subject["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . "</ul>";
                    }
                }
                
                //affiche une liste de synonymes sous forme de liens HTML
                if (array_key_exists("syn", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["syn"]) > 1) {

                    // On met sizeof($word_data[$word][$def_id]["syn"]) > 1 car le mot lui-même est présent dans les synonymes

                    $txt = "<p class=\"trad\" id=\"synonyme\">Synonymes : </p><p lang=\"ar\" dir=\"rtl\"> ";

                    $map = function($syn) use ($word, $protocol, $base_url, $word_data, $req_lang) {

                            if (array_key_exists($syn["value"], $word_data)) {
                                $link = "#".$syn["value"];
                            } else {
                                $link = $protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait/".urlencode($syn["value"]);
                            }
                            return "<i><a href=\"".$link."\">".$syn["value"]."</a></i>";
                        };

                    
                    $txt .= implode(" ، ", array_map($map, array_filter($word_data[$word][$def_id]["syn"], function($el) use ($word) {
                                if ($el["value"] != $word)
                                    return true;

                                return false;
                            })
                        )
                    );

                    $ex->innertext .= $txt . " </p>";

                }

                //section origine si elle existe
                if (array_key_exists("coverage", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["coverage"]) > 0) {
                    $txt = "<p class=\"trad\" id=\"origine\"> Origines : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["coverage"] as $coverage) {
                        #if ($coverage["lang"] == $langs[0][0]) {
                            #$one = true;
                            #$txt .= "<li lang=\"".$coverage["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?coverage=".urlencode($coverage["value"])."\">".$coverage["value"]."</a></li>";
                        #}
                        if (strcasecmp($coverage["lang"], $langs[0][0]) === 0) {
                            $one = true;
                            $txt .= "<li lang=\"".$coverage["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?coverage=".urlencode($coverage["value"])."\">".$coverage["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                //section prononciation si elle existe
                if (array_key_exists("pron", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["pron"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"pron\"> Prononciations : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["pron"] as $pron) {
                        if ($pron["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$pron["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?pron=".urlencode($pron["value"])."\">".$pron["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }


                //section étymologie (?)
                if (array_key_exists("etymo", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["etymo"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"etymo\"> Etymologie : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["etymo"] as $etymo) {
                        if ($etymo["lang"] == $langs[0][0]) {
                            $txt .= "<li lang=\"".$etymo["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url.(isset($req_lang) ? $req_lang."/" : "")."quiconnait?etymo=".urlencode($etymo["value"])."\">".$etymo["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                //affichage d'exemples sous forme de liste <ul> si ils existent
                if (array_key_exists("example", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["example"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"exemple\"> Exemples : </p>";
                    $txt .= "<ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["example"] as $example) {
                        if ($example["lang"] == "ar") {
                            $one = true;
                            $txt .= "<li lang=\"".$example["lang"]."\" dir=\"rtl\"><i>".$example["value"]."</i></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                $ex->innertext .= "<br/></li>";
            }

            // Formulaire de contact ajouté ici
            $ex->innertext .= "<div class=\"texte trad\" id=\"formulaire_quiconnait\">Blabla</div>";
            $contactUrl = $protocol . $base_url . $lang . "/contact";
            $ex->innertext .= "<a href=\"" . $protocol . $base_url . "contact\" class=\"trad_value\" id=\"bouton_quiconnait\" value=\"Contactez-nous\"/>Contact</a>";
    

            $ex->innertext .= "</br>";
            $ex->innertext .= "</ol>";
            //var_dump($template->innertext);
        }


    }
}

?>