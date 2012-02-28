<?php
/*
Copyright © 2012 Ophir LOJKINE <ophir.lojkine at free.fr>
 This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/*Configuration.
0 : do not parse, do not print
1 : print as simple text (do not apply any HTML tag or style)
2 : print  and apply all supported HTML tags and styles
*/
$OPHIR_CONF = array (
	"header" => 2,
	"list" => 2,
	"table" => 2,
	"footnote" => 2,
	"link" => 2,
	"image" => 2,
	"note" => 2,
	"annotation" => 2
);

function odt2xml ($odtFile){
	global $zip;
	$zip = new ZipArchive();
	$zip->open($odtFile) or die ("Error: Unable to open the document.");
	$contents = $zip->getFromName('content.xml');
	if($contents===FALSE) die("Error: Unable to access file contents.\n");
	

	$xml = new XMLReader();
	$xml->xml($contents); 
	return $xml;
}

function base64_encode_image ($filename,$filetype) {
    if ($filename) {
        $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        return 'data:image/' . $filetype . ';base64,' . base64_encode($imgbinary);
    }
}

function xml2html($xml){
	global $zip, $OPHIR_CONF;
	$html = "";

	$elementsTree = array();

	static $styles = array("Quotations" => array("tags" => array("blockquote")));

	$footnotes = "";

	if ($OPHIR_CONF["list"]===0) $translationTable["text:list"] = false; 
	elseif ($OPHIR_CONF["list"]===2){
		$translationTable["text:list"] = "ul";
		$translationTable["text:list-item"] = "li";
	}

	$translationTable = array();
	if ($OPHIR_CONF["table"]===0) $translationTable["table:table"] = false; 
	elseif ($OPHIR_CONF["table"]===2){
		$translationTable["table:table"] = "table cellspacing=0 cellpadding=0 border=1";
		$translationTable["table:table-row"] = "tr";
		$translationTable["table:table-cell"] = "td";
	}

	//$xml->read(); echo "\n<br><code>\n".str_replace("&lt", "\n<br><br>&lt", htmlspecialchars($xml->readInnerXML()))."\n</code><br>\n\n";
	while ($xml->read()) {
		//var_dump($xml->name, $xml->nodeType, $xml->isEmptyElement, ($xml->hasValue)?$xml->value:false); echo "\n<br>\n";
		$openedTags = array();//This array will contain the HTML tags opened in every iteration

		if ($xml->nodeType === XMLReader::END_ELEMENT){//Handle a closing tag
					if(empty($elementsTree)) continue;
					do {
						$element = array_pop($elementsTree);
						if ($element && $element["tags"]){
							//Close opened tags
							$element["tags"] = array_reverse($element["tags"]);
							foreach ($element["tags"] as $HTMLtag){
									//$html.= "<font style='color:red' title='Closing $HTMLtag, from $element[name]. Current element is ".($xml->name)."'>©</font>";
									$HTMLtag = current(explode(" ", $HTMLtag));
									$html .= "</".$HTMLtag.">";
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
			switch ($xml->name){
				case "#text"://Text
					//$html .= str_replace(array("<",">"),array("&lt;","&gt;"),$xml->value);
					$html .= htmlspecialchars($xml->value);
					break;
				case "text:h"://Title
					if ($OPHIR_CONF["header"]===0) {$xml->next();break;}
					elseif ($OPHIR_CONF["header"]===1) break;
					$n = $xml->getAttribute("text:outline-level");
					if ($n>6) $n=6;
					$openedTags[] = "h$n";
					$html .= "\n\n<h$n>";
					break;

				case "text:p"://Paragraph
					//Just convert odf <text:p> to html <p>
					$tags = @$styles[$xml->getAttribute("text:style-name")]["tags"];
					if (!($tags && !in_array("blockquote", $tags)))
					// Do not print a <p> immediatly after or before a <blockquote> 
					{
						$openedTags[] = "p";
						$html .= "\n<p>";
					}
					break;

				case "text:a":
					if ($OPHIR_CONF["link"]===0) {$xml->next();break;}
					elseif ($OPHIR_CONF["link"]===1) break;
					$href = $xml->getAttribute("xlink:href");
					$openedTags[] = 'a';
					$html .= '<a href="'.$href.'">';
					break;

				case "draw:image":
					if ($OPHIR_CONF["image"]===0) {$xml->next();break;}
					elseif ($OPHIR_CONF["image"]===1) break;
					$src = $xml->getAttribute("xlink:href");
					$src = 'data:image;base64,' . base64_encode($zip->getFromName($src));
					$html .= "\n<img src=\"$src\">";
					$openedTags[] = "img";
					break;

				case "style:style":
					$name = $xml->getAttribute("style:name");
					$parent = $xml->getAttribute("style:parent-style-name");
					if (array_key_exists($parent, $styles)) $styles[$name] = $styles[$parent]; //Not optimal

					if ($xml->isEmptyElement) break; //We can't handle that at the moment
					while( $xml->read() && //Read one tag
							($xml->name != "style:style" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
						)
					 {
						if($xml->name == "style:text-properties") {
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
					if ($OPHIR_CONF["note"]===0) {$xml->next(); break;}
					elseif ($OPHIR_CONF["note"]===1) break;
					$noteId = $xml->getAttribute("text:id");
					$noteName = "Note";
					while( $xml->read() && //Read one tag
							($xml->name != "text:note" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
						)
						{
						if ($xml->name=="text:note-citation" &&
						$xml->nodeType == XMLReader::ELEMENT)
							$noteName = $xml->readString();
						elseif ($xml->name=="text:note-body" &&
						$xml->nodeType == XMLReader::ELEMENT){
							$tmpReader = new XMLReader();
							$tmpReader->xml($xml->readOuterXML());
							$noteContent = xml2html($tmpReader);
						}
					}

					$html .= "<sup><a href=\"#odt-$noteId\" name=\"anchor-odt-$noteId\">$noteName</a></sup>";

					$footnotes .= "\n".'<div class="odt-footnotes" id="odt-'.$noteId.'" >';
					$footnotes .= '<a class="footnote-name" href="#anchor-odt-'.$noteId.'">'.$noteName.'.</a> ';
					$footnotes .= $noteContent;
					$footnotes .= '</div>'."\n";
					break;

				case "office:annotation":
					if ($OPHIR_CONF["annotation"]===0) {$xml->next();break;}
					elseif ($OPHIR_CONF["annotation"]===1) break;
					$annotationId = (isset($annotationId))?$annotationId+1:1;
					$annotationContent = "";
					$annotationCreator = "Anonymous";
					$annotationDate = "";
					do{
						$xml->read();
						if ($xml->name=="dc:creator" &&
						$xml->nodeType == XMLReader::ELEMENT)
							$annotationCreator = $xml->readString();
						elseif ($xml->name=="dc:date" &&
						$xml->nodeType == XMLReader::ELEMENT){
							$annotationDate = date("jS \of F Y, H\h i\m", strtotime($xml->readString()));
						}
						elseif ($xml->nodeType == XMLReader::ELEMENT){
							//echo "<h1>Contenu!!".$xml->readString()."</h1>";
							$annotationContent .= $xml->readString();
							$xml->next(); 
						}
					}while (!($xml->name === "office:annotation" &&
						$xml->nodeType === XMLReader::END_ELEMENT));//End of the note

					$html .= '<sup><a href="#odt-annotation-'.$annotationId.'" name="anchor-odt-annotation-'.$annotationId.'" title="Annotation ('.$annotationCreator.')">('.$annotationId.')</a></sup>';
					$footnotes .= "\n".'<div class="odt-annotation" id="odt-annotation-'.$annotationId.'" >';
					$footnotes .= '<a class="annotation-name" href="#anchor-odt-annotation-'.$annotationId.'"> ('.$annotationId.')&nbsp;</a>';
					$footnotes .= "\n".'<b>'.$annotationCreator.' (<i>'.$annotationDate.'</i>)</b> :';
					$footnotes .= "\n".'<div class="odt-annotation-content">'.$annotationContent.'</div>';
					$footnotes .= '</div>'."\n";
					break;
									
					default:
						if (array_key_exists($xml->name, $translationTable)){
							if ($translationTable[$xml->name]===false) {$xml->next(); break; }
							$tag = explode(" ", $translationTable[$xml->name], 1);
							//$tag[0] is the tag name, other indexes are attributes
							$openedTags[] = $tag[0];
							$html .= "\n<".$translationTable[$xml->name].">";
						}
			}
		}

		if ($xml->nodeType === XMLReader::ELEMENT  &&
			!($xml->isEmptyElement) ){ //Opening tag
			$currentElementStyle = $xml->getAttribute("text:style-name");
			if ($currentElementStyle &&
				array_key_exists ($currentElementStyle, $styles)){
				//Styling tags management
					foreach ($styles[$currentElementStyle]["tags"] as $HTMLtag){
						$html .= "<".$HTMLtag.">";
						$openedTags[] = $HTMLtag;
					}
			}
			$elementsTree[] = array ("name" => $xml->name,
									"tags" => $openedTags);
		}

	/*
	//Useful for debugging the $elementsTree stack
	foreach ($elementsTree as $level){
		echo $level["name"]." (".implode(" ", $level["tags"]).") > ";
	}
	echo "\n<br>".$xml->readString()."<br>";
	echo "<br>\n";
	*/
		
	}
	return $html.$footnotes;
} 

function odt2html ($odtFile){
	return xml2html(odt2xml($odtFile));
}

?>
