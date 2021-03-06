<?php
/**
* @author Thomas Pellissier Tanon
* @copyright 2011 Thomas Pellissier Tanon
* @licence http://www.gnu.org/licenses/gpl.html GNU General Public Licence
*/

/**
* create an epub 2 file
* @see http://idpf.org/epub/201
*/
class Epub2Generator implements Generator {

        /**
        * array key/value that contain translated strings
        */
        protected $i18n = array();

        /**
        * return the extension of the generated file
        * @return string
        */
        public function getExtension() {
                return 'epub';
        }

        /**
        * return the mimetype of the generated file
        * @return string
        */
        public function getMimeType() {
                return 'application/epub+zip';
        }

        /**
        * create the file
        * @var $data Book the title of the main page of the book in Wikisource
        * @return string
        * @todo images, cover, about...
        */
        public function create(Book $book) {
                $css = $this->getCss($book);
                $this->i18n = getI18n($book->lang);
                setLocale(LC_TIME, $book->lang . '_' . strtoupper($book->lang));
                $wsUrl = wikisourceUrl($book->lang, $book->title);
                $cleaner = new BookCleanerEpub();
                $cleaner->clean($book);
                $zip = new ZipCreator();
                $zip->addContentFile('mimetype', 'application/epub+zip', null, false); //the mimetype must be first and uncompressed
                $zip->addContentFile('META-INF/container.xml', $this->getXmlContainer());
                $zip->addContentFile('OPS/content.opf', $this->getOpfContent($book, $wsUrl));
                $zip->addContentFile('OPS/toc.ncx', $this->getNcxToc($book));
                if($book->cover != '')
                        $zip->addContentFile('OPS/cover.xhtml', $this->getXhtmlCover($book));
                $zip->addContentFile('OPS/title.xhtml', $this->getXhtmlTitle($book));
                $zip->addContentFile('OPS/about.xhtml', $this->getXhtmlAbout($book, $wsUrl));
                $dir = dirname(__FILE__);
                $zip->addFile($dir.'/images/Accueil_scribe.png', 'OPS/images/Accueil_scribe.png');
                if($book->options['fonts']) {
                        $zip->addFile($dir.'/fonts/FreeSerif.otf', 'OPS/fonts/FreeSerif.otf');
                        $zip->addFile($dir.'/fonts/FreeSerifBold.otf', 'OPS/fonts/FreeSerifBold.otf');
                        $zip->addFile($dir.'/fonts/FreeSerifBoldItalic.otf', 'OPS/fonts/FreeSerifBoldItalic.otf');
                        $zip->addFile($dir.'/fonts/FreeSerifItalic.otf', 'OPS/fonts/FreeSerifItalic.otf');
                }
                if($book->content)
                        $zip->addContentFile('OPS/' . $book->title . '.xhtml', $book->content->saveXML());
                if(!empty($book->chapters)) {
                        foreach($book->chapters as $chapter) {
                                $zip->addContentFile('OPS/' . $chapter->title . '.xhtml', $chapter->content->saveXML());
                                foreach($chapter->chapters as $subpage) {
                                        $zip->addContentFile('OPS/' . $subpage->title . '.xhtml', $subpage->content->saveXML());
                                }
                        }
                }
                foreach($book->pictures as $picture) {
                        $zip->addContentFile('OPS/images/' . $picture->title, $picture->content);
                }
                $zip->addContentFile('OPS/main.css', $css);
                return $zip->getContent();
        }

        protected function getXmlContainer() {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
                                <rootfiles>
                                        <rootfile full-path="OPS/content.opf" media-type="application/oebps-package+xml" />
                                </rootfiles>
                        </container>';
                return $content;
        }

        protected function getOpfContent(Book $book, $wsUrl) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="uid" version="2.0">
                                <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:dcterms="http://purl.org/dc/terms/">
                                        <dc:identifier id="uid" opf:scheme="URI">' . $wsUrl . '</dc:identifier>
                                        <dc:language xsi:type="dcterms:RFC4646">' . $book->lang . '</dc:language>
                                        <dc:title>' . $book->name . '</dc:title>
                                        <dc:source>' . $wsUrl . '</dc:source>
                                        <dc:date opf:event="ops-publication" xsi:type="dcterms:W3CDTF">' . date(DATE_W3C) . '</dc:date>
                                        <dc:rights>http://creativecommons.org/licenses/by-sa/3.0/</dc:rights>
                                        <dc:rights>http://www.gnu.org/copyleft/fdl.html</dc:rights>
                                        <dc:contributor opf:role="bkp">Wikisource</dc:contributor>';
                                if($book->author != '') {
                                        $content.= '<dc:creator opf:role="aut">' . $book->author . '</dc:creator>';
                                }
                                if($book->translator != '') {
                                        $content.= '<dc:contributor opf:role="trl">' . $book->translator . '</dc:contributor>';
                                }
                                if($book->illustrator != '') {
                                        $content.= '<dc:contributor opf:role="ill">' . $book->illustrator . '</dc:contributor>';
                                }
                                if($book->publisher != '') {
                                        $content.= '<dc:publisher>' . $book->publisher . '</dc:publisher>';
                                }
                                if($book->year != '') {
                                        $content.= '<dc:date opf:event="original-publication">' . $book->year . '</dc:date>';
                                }
                                if($book->cover != '') {
                                        $content.= '<meta name="cover" content="cover" />';
                                } else {
                                        $content.= '<meta name="cover" content="title" />';
                                }
                                $content.= '</metadata>
                                <manifest>
                                        <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>';
                                        if($book->cover != '')
                                                $content.= '<item id="cover" href="cover.xhtml" media-type="application/xhtml+xml" />';
                                        $content.= '<item id="title" href="title.xhtml" media-type="application/xhtml+xml" />
                                        <item id="mainCss" href="main.css" media-type="text/css" />
                                        <item id="Accueil_scribe.png" href="images/Accueil_scribe.png" media-type="image/png" />';
                                        if($book->options['fonts']) {
                                                $content.= '<item id="FreeSerif" href="fonts/FreeSerif.otf" media-type="font/opentype" />
                                                        <item id="FreeSerifBold" href="fonts/FreeSerifBold.otf" media-type="font/opentype" />
                                                        <item id="FreeSerifBoldItalic" href="fonts/FreeSerifBoldItalic.otf" media-type="font/opentype" />
                                                        <item id="FreeSerifItalic" href="fonts/FreeSerifItalic.otf" media-type="font/opentype" />';
                                        }
                                        if($book->content)
                                                $content.= '<item id="' . $book->title . '" href="' . $book->title . '.xhtml" media-type="application/xhtml+xml" />' . "\n";
                                        foreach($book->chapters as $chapter) {
                                                $content.= '<item id="' . $chapter->title . '" href="' . $chapter->title . '.xhtml" media-type="application/xhtml+xml" />' . "\n";
                                                foreach($chapter->chapters as $subpage) {
                                                        $content.= '<item id="' . $subpage->title . '" href="' . $subpage->title . '.xhtml" media-type="application/xhtml+xml" />' . "\n";
                                                }
                                        }
                                        foreach($book->pictures as $picture) {
                                                $content.= '<item id="' . $picture->title . '" href="images/' . $picture->title . '" media-type="' . $picture->mimetype . '" />' . "\n";
                                        }
                                        $content.= '<item id="about" href="about.xhtml" media-type="application/xhtml+xml" />
                                </manifest>
                                <spine toc="ncx">';
                                        if($book->cover != '')
                                                $content.= '<itemref idref="cover" linear="yes" />';
                                        $content.= '<itemref idref="title" linear="yes" />';
                                        if($book->content)
                                                $content.= '<itemref idref="' . $book->title . '" linear="yes" />';
                                        if(!empty($book->chapters)) {
                                                foreach($book->chapters as $chapter) {
                                                        $content.= '<itemref idref="' . $chapter->title . '" linear="yes" />';
                                                        foreach($chapter->chapters as $subpage) {
                                                                $content.= '<itemref idref="' . $subpage->title . '" linear="yes" />';
                                                        }
                                                }
                                        }
                                        $content.= '<itemref idref="about" linear="yes" />
                                </spine>
                                <guide>';
                                        if($book->cover != '')
                                                $content.= '<reference type="cover" title="' . $this->i18n['cover'] . '" href="cover.xhtml" />';
                                        else
                                                $content.= '<reference type="cover" title="' . $this->i18n['cover'] . '" href="title.xhtml" />';
                                        $content.= '<reference type="title-page" title="' . $this->i18n['title_page'] . '" href="title.xhtml" />';
                                        if($book->content)
                                                    $content.= '<reference type="text" title="' . $book->name . '" href="' . $book->title . '.xhtml" />';
                                        $content.= '<reference type="copyright-page" title="' . $this->i18n['about'] . '" href="about.xhtml" />
                                </guide>
                        </package>';
                return $content;
        }

        protected function getNcxToc(Book $book) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <!DOCTYPE ncx PUBLIC "-//NISO//DTD ncx 2005-1//EN" "http://www.daisy.org/z3986/2005/ncx-2005-1.dtd">
                        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
                                <head>
                                        <meta name="dtb:uid" content="' . wikisourceUrl($book->lang, $book->title) . '" />
                                        <meta name="dtb:depth" content="1" />
                                        <meta name="dtb:totalPageCount" content="0" />
                                        <meta name="dtb:maxPageNumber" content="0" />
                                </head>
                                <docTitle><text>' . $book->name . '</text></docTitle>
                                <docAuthor><text>' . $book->author . '</text></docAuthor>
                                <navMap>
                                        <navPoint id="title" playOrder="1">
                                                <navLabel><text>' . $this->i18n['title_page']  . '</text></navLabel>
                                                <content src="title.xhtml"/>
                                        </navPoint>';
                                        $order = 2;
                                        if($book->content) {
                                            $content.= '<navPoint id="' . $book->title . '" playOrder="' . $order . '">
                                                    <navLabel><text>' . $book->name . '</text></navLabel>
                                                    <content src="' . $book->title . '.xhtml" />
                                            </navPoint>';
                                            $order++;
                                        }
                                        if(!empty($book->chapters)) {
                                                foreach($book->chapters as $chapter) {
                                                        if($chapter->name != '') {
                                                                $content.= '<navPoint id="' . $chapter->title . '" playOrder="' . $order . '">
                                                                            <navLabel><text>' . $chapter->name . '</text></navLabel>
                                                                            <content src="' . $chapter->title . '.xhtml" />';
                                                                $order++;
                                                                foreach($chapter->chapters as $subpage) {
                                                                        if($subpage->name != '') {
                                                                                $content.= '<navPoint id="' . $subpage->title . '" playOrder="' . $order . '">
                                                                                            <navLabel><text>' . $subpage->name . '</text></navLabel>
                                                                                            <content src="' . $subpage->title . '.xhtml" />
                                                                                </navPoint>';
                                                                                $order++;
                                                                        }
                                                                }
                                                                $content.= '</navPoint>';
                                                        }
                                                }
                                        }
                                        $content.= '<navPoint id="about" playOrder="' . $order . '">
                                                <navLabel>
                                                        <text>' . $this->i18n['about'] . '</text>
                                                </navLabel>
                                                <content src="about.xhtml"/>
                                        </navPoint>
                               </navMap>
                        </ncx>';
                return $content;
        }

        protected function getXhtmlCover(Book $book) {
                $content = '<div style="text-align: center; page-break-after: always;">
                                    <img src="images/' . $book->pictures[$book->cover]->title . '" alt="Cover" style="height: 100%; max-width: 100%;" />
                            </div>';
                return getXhtmlFromContent($book->lang, $content, $book->name);
        }

        protected function getXhtmlTitle(Book $book) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <!DOCTYPE html>
                        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $book->lang . '">
                                <head>
                                        <title>' . $book->name . '</title>
                                        <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
                                        <link type="text/css" rel="stylesheet" href="main.css" />
                                </head>
                                <body style="background-color:ghostwhite;"><div style="text-align:center; position:absolute;">
                                        <h1 id="heading_id_2">' . $book->name . '</h1>
                                        <h2>' . $book->author . '</h2>
                                        <br />
                                        <br />
                                        <img alt="" src="images/Accueil_scribe.png" />
                                        <br />
                                        <h4>' . $book->publisher;
                                        if($book->publisher != '' && ($book->year != '' || $book->place != ''))
                                                $content .= ', ';
                                        $content .= $book->place;
                                        if($book->year != '' && $book->place != '')
                                                $content .= ', ';
                                        $content .= $book->year . '</h4>
                                        <br style="margin-top: 3em; margin-bottom: 3em; border: none; background: black; width: 8em; height: 1px; display: block;" />
                                        <h5>' . str_replace('%d', strftime('%x'), $this->i18n['exported_from_wikisource_the']) . '</h5>
                                </div></body>
                        </html>';
                return $content;
        }

        protected function getXhtmlAbout(Book $book, $wsUrl) {
                $list = '';
                $listBot = '';
                foreach($book->credits as $name => $value) {
                        if(in_array('bot', $value['flags']))
                                $listBot .= '<li>' . $name . "</li>\n";
                        else
                                $list .= '<li>' . $name . "</li>\n";
                }
                $about = getTempFile($book->lang, 'about.xhtml');
                if($about == '') {
                        $about = getXhtmlFromContent($book->lang, $list, $this->i18n['about']);
                } else {
                        $about = str_replace('{CONTRIBUTORS}', '<ul>'.$list.'</ul>', $about);
                        $about = str_replace('{BOT-CONTRIBUTORS}', '<ul>'.$list.'</ul>', $about);
                        $about = str_replace('{URL}', $wsUrl, $about);
                }
                return $about;
        }

        protected function getCss(Book $book) {
                $css = '';
                if($book->options['fonts']) {
                        $css .= '@font-face { font-family : "FreeSerif"; font-weight : normal; font-style: normal; src: url("fonts/FreeSerif.otf"); }
                                @font-face { font-family : "FreeSerif"; font-weight : bold; font-style: normal; src: url("fonts/FreeSerifBold.otf"); }
                                @font-face { font-family : "FreeSerif"; font-weight : normal; font-style: italic; src: url("fonts/FreeSerifItalic.otf"); }
                                @font-face { font-family : "FreeSerif"; font-weight : bold; font-style: italic; src: url("fonts/FreeSerifBoldItalic.otf"); }
                                body { font-family: FreeSerif, Arial, serif; }' ."\n\n";
                }
                $css .= getTempFile($book->lang, 'epub.css');
                return $css;
        }
}

/**
* Clean and modify book content in order to epub generation
*/
class BookCleanerEpub {
        protected $book = null;
        protected $linksList = array();

        public function clean(Book $book) {
                $this->book = $book;

                $this->encodeTitles();
                $this->splitChapters();

                if($book->content) {
                        $xPath = $this->getXPath($book->content);
                        $this->setHtmlTitle($xPath, $book->name);
                        $this->cleanHtml($xPath);
                }
                foreach($this->book->chapters as $chapter) {
                        $xPath = $this->getXPath($chapter->content);
                        $this->setHtmlTitle($xPath, $chapter->name);
                        $this->cleanHtml($xPath);
                        foreach($chapter->chapters as $subpage) {
                                $xPath = $this->getXPath($subpage->content);
                                $this->setHtmlTitle($xPath, $subpage->name);
                                $this->cleanHtml($xPath);
                        }
                }
         }

        protected function splitChapters() {
                $chapters = array();
                if($this->book->content) {
                        $main = $this->splitChapter($this->book);
                        $this->book->content = $main[0]->content;
                        if(!empty($main)) {
                                unset($main[0]);
                                $chapters = $main;
                        }
                }
                foreach($this->book->chapters as $chapter) {
                        $chapters = array_merge($chapters, $this->splitChapter($chapter));
                }
                $this->book->chapters = $chapters;
        }

        /*
         * Credit for the tricky part of this code: Asbjorn Grandt
         * https://github.com/Grandt/PHPePub/blob/master/EPubChapterSplitter.php
         */
        protected function splitChapter($chapter) {
                $partSize = 250000;
                $length = strlen($chapter->content->saveXML());
                if($length <= $partSize)
                        return array($chapter);

                $pages = array();

                $files = array();
                $domDepth = 0;
                $domPath = array();
                $domClonedPath = array();

                $curFile = $chapter->content->createDocumentFragment();
                $files[] = $curFile;
                $curParent = $curFile;
                $curSize = 0;

                $body = $chapter->content->getElementsbytagname("body");
                $node = $body->item(0)->firstChild;
                do {
                        $nodeData = $chapter->content->saveXML($node);
                        $nodeLen = strlen($nodeData);

                        if ($nodeLen > $partSize && $node->hasChildNodes()) {
                                $domPath[] = $node;
                                $domClonedPath[] = $node->cloneNode(false);
                                $domDepth++;

                                $node = $node->firstChild;
                        }

                        $next_node = $node->nextSibling;

                        if ($node != null && $node->nodeName != "#text") {
                                if ($curSize > 0 && $curSize + $nodeLen > $partSize) {
                                        $curFile = $chapter->content->createDocumentFragment();
                                        $files[] = $curFile;
                                        $curParent = $curFile;
                                        if ($domDepth > 0) {
                                                reset($domPath);
                                                reset($domClonedPath);
                                                while (list($k, $v) = each($domClonedPath)) {
                                                        $newParent = $v->cloneNode(false);
                                                        $curParent->appendChild($newParent);
                                                        $curParent = $newParent;
                                                }
                                        }
                                        $curSize = strlen($chapter->content->saveXML($curFile));
                                }
                                $curParent->appendChild($node->cloneNode(true));
                                $curSize += $nodeLen;
                        }

                        $node = $next_node;
                        while ($node == null && $domDepth > 0) {
                                $domDepth--;
                                $node = end($domPath)->nextSibling;
                                array_pop($domPath);
                                array_pop($domClonedPath);
                                $curParent = $curParent->parentNode;
                        }
                } while ($node != null);

                foreach($files as $idx => $file) {
                        $xml = $this->getEmptyDom();
                        $body = $xml->getElementsByTagName("body")->item(0);
                        $body->appendChild($xml->importNode($file, true));
                        $page = new Page();
                        if($idx == 0) {
                                $page->title = $chapter->title;
                                $page->name = $chapter->name;
                        } else {
                                $page->title = $chapter->title . '_' . ($idx + 1);
                        }
                        $page->content = $xml;
                        $pages[] = $page;
                }
                return $pages;
        }

        protected function encodeTitles() {
                $this->book->title = $this->encode($this->book->title);
                $this->linksList[] = $this->book->title . '.xhtml';
                foreach($this->book->chapters as $chapter) {
                        $chapter->title = $this->encode($chapter->title);
                        $this->linksList[] = $chapter->title . '.xhtml';
                        foreach($chapter->chapters as $subpage) {
                                $subpage->title = $this->encode($subpage->title);
                                $this->linksList[] = $subpage->title . '.xhtml';
                        }
                }
                foreach($this->book->pictures as $picture) {
                        $picture->title = $this->encode($picture->title);
                        $this->linksList[] = $picture->title;
                }
        }

        protected function getXPath($file) {
                $xPath = new DOMXPath($file);
                $xPath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
                return $xPath;
        }

        protected function getEmptyDom() {
                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->loadXML(getXhtmlFromContent($this->book->lang, ''));
                return $dom;
        }

        protected function encode($string) {
                $search = array('[αάàâäΑÂÄ]','[βΒ]','[Ψç]','[δΔ]','[εéèêëΕÊË]','[η]', '[φϕΦ]','[γΓ]','[θΘ]','[ιîïΙÎÏ]','[Κκ]','[λΛ]','[μ]','[ν]','[οôöÔÖ]','[Ωω]','[πΠ]','[Ψψ]','[ρΡ]','[σΣ]', '[τ]','[υûùüΥÛÜ]','[ξΞ]','[ζΖ]','[ ]','[^a-zA-Z0-9_\.]');
                $replace = array('a','b','c','d','e','eh','f','g','h','i','k','l','m','n','o','oh','p','ps','r','s','t','u','x','z','_','_');
                mb_regex_encoding('UTF-8');
                foreach($search as $i => $pat) {
                       $string = mb_eregi_replace($pat, $replace[$i], $string);
                }
                return utf8_decode($string);
        }

        /**
        * modified the XHTML
        */
        protected function cleanHtml(DOMXPath $xPath) {
            $this->setPictureLinks($xPath);
                $dom = $xPath->document;
                $this->setLinks($dom);
        }

        /**
        * change the picture links
        */
        protected function setHtmlTitle(DOMXPath $xPath, $name) {
                $title = $xPath->query('/html:html/html:head/html:title')->item(0);
                $title->nodeValue = $name;
        }

        /**
        * change the picture links
        */
        protected function setPictureLinks(DOMXPath $xPath) {
                $list = $xPath->query('//html:img');
                foreach($list as $node) {
                        $title = $this->encode($node->getAttribute('alt'));
                        if(in_array($title, $this->linksList))
                                $node->setAttribute('src', 'images/' . $title);
                        else
                                $node->parentNode->removeChild($node);
                }
        }

        /**
        * change the internal links
        */
        protected function setLinks(DOMDocument $dom) {
                $list = $dom->getElementsByTagName('a');
                $title = Api::mediawikiUrlEncode($this->book->title);
                foreach($list as $node) {
                        $href = $node->getAttribute('href');
                        $title = $this->encode($node->getAttribute('title')) . '.xhtml';
                        if($href[0] == '#') {
                                continue;
                        } elseif(in_array($title, $this->linksList)) {
                                $pos = strpos($href, '#');
                                if ($pos !== false) {
                                        $anchor = substr($href, $pos + 1);
                                        if(is_numeric($anchor)) {
                                                $title .= '#_' . $anchor;
                                        } else {
                                                $title .= '#' . $anchor;
                                        }
                                }
                                $node->setAttribute('href', $title);
                        } else {
                               $node->setAttribute('href', 'http:'.$href);
                        }
                }
        }
}
