<?php
/**
 * @file
 * ODT to HTML conversion functions
 *
 * Two functions are defined here : the first extracts the contents as XML from
 * the ODT file. The second parses the XML to produce HTML.
 */

/* Configuration.
 0 : do not parse, do not print
 1 : print as simple text (do not apply any HTML tag or style)
 2 : print  and apply all supported HTML tags and styles
 */
$OPHIR_CONF = array(
  "header" => 2,
  "list" => 2,
  "table" => 2,
  "footnote" => 2,
  "link" => 2,
  "image" => 2,
  "note" => 2,
  "annotation" => 2
);

function odt2xml($odt_file) {
  global $_ophir_odt_import_zip;
  $_ophir_odt_import_zip = new ZipArchive();
  $_ophir_odt_import_zip->open($odt_file) or die("Error: Unable to open the document. ");
  $contents = $_ophir_odt_import_zip->getFromName('content.xml');
  if ($contents===FALSE) die("Error: Unable to access file contents.\n");


  $xml = new XMLReader();
  $xml->xml($contents);
  return $xml;
}

function base64_encode_image($filename, $filetype) {
    if ($filename) {
        $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        return 'data:image/' . $filetype . ';base64,' . base64_encode($imgbinary);
    }
}

function xml2html($xml) {
  global $_ophir_odt_import_zip, $OPHIR_CONF;
  $html = "";

  $elements_tree = array();

  static $styles = array("Quotations" => array("tags" => array("blockquote")));

  $footnotes = "";

  if ($OPHIR_CONF["list"]===0) $translation_table["text:list"] = FALSE;
  elseif ($OPHIR_CONF["list"]===2) {
    $translation_table["text:list"] = "ul";
    $translation_table["text:list-item"] = "li";
  }

  $translation_table = array();
  if ($OPHIR_CONF["table"]===0) $translation_table["table:table"] = FALSE;
  elseif ($OPHIR_CONF["table"]===2) {
    $translation_table["table:table"] = "table cellspacing=0 cellpadding=0 border=1";
    $translation_table["table:table-row"] = "tr";
    $translation_table["table:table-cell"] = "td";
  }

  //$xml->read(); echo "\n<br><code>\n" .str_replace("&lt", "\n<br><br>&lt", htmlspecialchars($xml->readInnerXML())). "\n</code><br>\n\n";
  while ($xml->read()) {
    //var_dump($xml->name, $xml->nodeType, $xml->isEmptyElement, ($xml->hasValue)?$xml->value:FALSE); echo "\n<br>\n";
    $opened_tags = array();//This array will contain the HTML tags opened in every iteration

    if ($xml->nodeType === XMLReader::END_ELEMENT) {//Handle a closing tag
          if (empty($elements_tree)) continue;
          do {
            $element = array_pop($elements_tree);
            if ($element && $element["tags"]) {
              //Close opened tags
              $element["tags"] = array_reverse($element["tags"]);
              foreach ($element["tags"] as $HTML_tag) {
                  //$html.= "<font style='color:red' title='Closing $HTML_tag, from $element[name]. Current element is " .($xml->name). "'>©</font>";
                  $HTML_tag = current(explode(" ", $HTML_tag));
                  $html .= "</" . $HTML_tag . ">";
              }
            }
          } while ($xml->name !== $element["name"] && $element); //Close every opened tags. This should also handle malformed XML files
          continue;
    }
    elseif (in_array($xml->nodeType,
        array(XMLReader::ELEMENT,
            XMLReader::TEXT,
            XMLReader::SIGNIFICANT_WHITESPACE))
        ) {//Handle tags
      switch ($xml->name) {
        case "#text"://Text
          //$html .= str_replace(array("<",">"),array("&lt;","&gt;"), $xml->value);
          $html .= htmlspecialchars($xml->value);
          break;
        case "text:h"://Title
          if ($OPHIR_CONF["header"]===0) {
            $xml->next();
            break;
          }
          elseif ($OPHIR_CONF["header"]===1) break;
          $n = $xml->getAttribute("text:outline-level");
          if ($n>6) $n=6;
          $opened_tags[] = "h$n";
          $html .= "\n\n<h$n>";
          break;

        case "text:p"://Paragraph
          //Just convert odf <text:p> to html <p>
          $tags = @$styles[$xml->getAttribute("text:style-name")]["tags"];
          if (!($tags && !in_array("blockquote", $tags))) {
          // Do not print a <p> immediatly after or before a <blockquote>
            $opened_tags[] = "p";
            $html .= "\n<p>";
          }
          break;

        case "text:a":
          if ($OPHIR_CONF["link"]===0) {
            $xml->next();
            break;
          }
          elseif ($OPHIR_CONF["link"]===1) break;
          $href = $xml->getAttribute("xlink:href");
          $opened_tags[] = 'a';
          $html .= '<a href="' . $href . '">';
          break;

        case "draw:image":
          if ($OPHIR_CONF["image"]===0) {
            $xml->next();
            break;
          }
          elseif ($OPHIR_CONF["image"]===1) break;
          $src = $xml->getAttribute("xlink:href");
          $src = 'data:image;base64,' . base64_encode($_ophir_odt_import_zip->getFromName($src));
          $html .= "\n<img src=\"$src\">";
          $opened_tags[] = "img";
          break;

        case "style:style":
          $name = $xml->getAttribute("style:name");
          $parent = $xml->getAttribute("style:parent-style-name");
          if (array_key_exists($parent, $styles)) $styles[$name] = $styles[$parent]; //Not optimal

          if ($xml->isEmptyElement) break; //We can't handle that at the moment
          while ( $xml->read() && //Read one tag
              ($xml->name != "style:style" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
            ) {
            if ($xml->name == "style:text-properties") {
              if ($xml->getAttribute("fo:font-style") == "italic")
                  $styles[$name]["tags"][] = "i"; //Creates the style and add <i> to its tags

              if ($xml->getAttribute("fo:font-weight") == "bold")
                  $styles[$name]["tags"][] = "b"; //Creates the style and add <b> to its tags

              if ($xml->getAttribute("style:text-underline-style") == "solid")
                  $styles[$name]["tags"][] = "u"; //Creates the style and add <b> to its tags

            }
          }
          break;
        case "text:note":
          if ($OPHIR_CONF["note"]===0) {
            $xml->next();
            break;
          }
          elseif ($OPHIR_CONF["note"]===1) break;
          $note_id = $xml->getAttribute("text:id");
          $note_name = "Note";
          while ( $xml->read() && //Read one tag
              ($xml->name != "text:note" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
            ) {
            if ($xml->name=="text:note-citation" &&
            $xml->nodeType == XMLReader::ELEMENT)
              $note_name = $xml->readString();
            elseif ($xml->name=="text:note-body" &&
            $xml->nodeType == XMLReader::ELEMENT) {
              $tmp_reader = new XMLReader();
              $tmp_reader->xml($xml->readOuterXML());
              $note_content = xml2html($tmp_reader);
            }
          }

          $html .= "<sup><a href=\"#odt-$note_id\" name=\"anchor-odt-$note_id\">$note_name</a></sup>";

          $footnotes .= "\n" . '<div class="odt-footnotes" id="odt-' . $note_id . '" >';
          $footnotes .= '<a class="footnote-name" href="#anchor-odt-' . $note_id . '">' . $note_name . ' .</a> ';
          $footnotes .= $note_content;
          $footnotes .= '</div>' . "\n";
          break;

        case "office:annotation":
          if ($OPHIR_CONF["annotation"]===0) {
    $xml->next();
    break;
    }
          elseif ($OPHIR_CONF["annotation"]===1) break;
          $annotation_id = (isset($annotation_id))?$annotation_id+1:1;
          $annotation_content = "";
          $annotation_creator = "Anonymous";
          $annotation_date = "";
          do{
            $xml->read();
            if ($xml->name=="dc:creator" &&
            $xml->nodeType == XMLReader::ELEMENT)
              $annotation_creator = $xml->readString();
            elseif ($xml->name=="dc:date" &&
            $xml->nodeType == XMLReader::ELEMENT) {
              $annotation_date = date("jS \of F Y, H\h i\m", strtotime($xml->readString()));
            }
            elseif ($xml->nodeType == XMLReader::ELEMENT) {
              //echo "<h1>Contenu!!" . $xml->readString(). "</h1>";
              $annotation_content .= $xml->readString();
              $xml->next();
            }
          }while (!($xml->name === "office:annotation" &&
            $xml->nodeType === XMLReader::END_ELEMENT));//End of the note

          $html .= '<sup><a href="#odt-annotation-' . $annotation_id . '" name="anchor-odt-annotation-' . $annotation_id . '" title="Annotation (' . $annotation_creator . ')">(' . $annotation_id . ')</a></sup>';
          $footnotes .= "\n" . '<div class="odt-annotation" id="odt-annotation-' . $annotation_id . '" >';
          $footnotes .= '<a class="annotation-name" href="#anchor-odt-annotation-' . $annotation_id . '"> (' . $annotation_id . ')&nbsp;</a>';
          $footnotes .= "\n" . '<b>' . $annotation_creator . ' (<i>' . $annotation_date . '</i>)</b> :';
          $footnotes .= "\n" . '<div class="odt-annotation-content">' . $annotation_content . '</div>';
          $footnotes .= '</div>' . "\n";
          break;

          default:
            if (array_key_exists($xml->name, $translation_table)) {
              if ($translation_table[$xml->name]===FALSE) {
                $xml->next();
                break;
              }
              $tag = explode(" ", $translation_table[$xml->name], 1);
              //$tag[0] is the tag name, other indexes are attributes
              $opened_tags[] = $tag[0];
              $html .= "\n<" . $translation_table[$xml->name] . ">";
            }
      }
    }

    if ($xml->nodeType === XMLReader::ELEMENT  &&
      !($xml->isEmptyElement) ) { //Opening tag
      $current_element_style = $xml->getAttribute("text:style-name");
      if ($current_element_style &&
        isset($styles[$current_element_style])) {
        //Styling tags management
          foreach ($styles[$current_element_style]["tags"] as $HTML_tag) {
            $html .= "<" . $HTML_tag . ">";
            $opened_tags[] = $HTML_tag;
          }
      }
      $elements_tree[] = array("name" => $xml->name,
                  "tags" => $opened_tags);
    }

  /*
  //Useful for debugging the $elements_tree stack
  foreach ($elements_tree as $level) {
    echo $level["name"]. " (" .implode(" ", $level["tags"]). ") > ";
  }
  echo "\n<br>" . $xml->readString(). "<br>";
  echo "<br>\n";
  */

  }
  return $html . $footnotes;
}

function odt2html($odt_file) {
  return xml2html(odt2xml($odt_file));
}
