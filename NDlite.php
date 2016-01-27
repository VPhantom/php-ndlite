<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// Class: NDlite
//
// Extract NaturalDocs and PHPDoc compatible documentation from source code.

// Section: Introduction

// Topic: Legal
//
// Copyright (C) 2008-2010 Stephane Lavergne <http://www.imars.com/>
//
// This program is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the
// Free Software Foundation, either version 3 of the License, or (at your
// option) any later version.
//
// This program is distributed in the hope that it will be useful, but
// *WITHOUT ANY WARRANTY*; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
// Public License for more details.
//
// You should have received a copy of the GNU General Public License along
// with this program. If not, see <http://www.gnu.org/licenses/>.

// Topic: Description
//
// Useful for public and private API documentation, but also for plain text
// files with minimal formatting. Implements a subset of the excellent
// <www.NaturalDocs.org> philosophy and rudimentary
// <http://en.wikipedia.org/wiki/PHPDoc> as well.
//
// If you just need a quick inline documentation parser with the
// NaturalDocs philosophy, or a basic way to view your PHPDoc documented
// source files with a single-file utility, this is it.  Note that PHPDoc
// support understands @param and @return natively, and displays all other
// tags in "Name: contents" fashion.  This isn't intended to replace
// phpDocumentor and others, but to provide a quick and easy to use
// alternative when you need one, as I did.
//
// Example:
//
// (begin example)
// $doc = new NDlite();
// $doc->parseFile($path_to_my_source_file);
// echo "<h1>Developer documentation for: " . $doc->guessTitle() . "</h1>\n";
// echo $doc->toXHTML(array('private' => true));
// (end example)
//
// TODO:
// * Document exactly what is supported...
//
// Known Bugs:
//
// * I use glob() with a prefix path, part of the PATH_INFO and ".*". In
//   unsafe installations of PHP, I'll bet this could allow malicious users
//   to form a PATH_INFO which could make NDlite read unintended files. For
//   small regular files, this is mostly harmless because the chances of
//   NDlite recognizing displayable comments is rather low. I see a
//   possible issue with large files using all of the PHP process' allowed
//   memory on each call. I also see an issue with devices and pipes, which
//   could stall NDlite processes waiting for input without reaching their
//   CPU clock limit if no wall clock limit is in force.
//
// Future Developments:
//
// * I'd like to see a "totroff()" for creating troff output for man pages.
//   I then could switch from POD to NDlite in my shell scripts.
//
// * Improve plurals and possessives to match NaturalDocs' versatility.
//
// * Add language parameter so that I can add French quote substitutions.
//
// * Further parse function prototypes, like NaturalDocs.
//

// Private Group: Constants

// Private Constant: NDLITE_K_GROUP
define('NDLITE_K_GROUP', 1);

// Private Constant: NDLITE_K_CHILD
define('NDLITE_K_CHILD', 2);

// Private Constant: NDLITE_K_GENERIC
define('NDLITE_K_GENERIC', 3);

class NDlite
{

    // Private Group: Properties

    // Private Property: grabber
    //
    // Our super-duper comment+code extracting PCRE.
    //
    // 1. Comment has to be one of:
    //	 Consecutive lines with initial non-whitespace '//' or '#'
    //	 Block starting with '/*' ending ungreedy with '*/'
    //	 Block starting with "=begin nd|NaturalDocs|Natural Docs",
    //	   ending ungreedy with "=cut" or "=end"
    //
    // 2. First line of LINES-BASED comment must contain ':' and exclude '$'
    //	 before the ':' to avoid CVS keywords.
    //	 Not enforced at this level for block comments.
    //
    // 3. Following is considered code until ';{/#'.
    //
    private $grabber = "/((\/\*.*?\*\/)|(=begin ((nd)|(natural ?docs))\r?\n.*?=((end)|(cut))[^\r\n]*\r?\n)|((((\/\/)|#)[^\r\n:$]*:[^\r\n:]*\r?\n)(([ \t]*((\/\/)|#)[^\r\n]*)\r?\n)*))([^;\{\/#]+)/si";
    
    // (
    //   (\/\*.*?\*\/)
    //   |(=begin ((nd)|(natural ?docs))\r?\n.*?=((end)|(cut))[^\r\n]*\r?\n)
    //   |((((\/\/)|#)[^\r\n:$]*:[^\r\n:]*\r?\n)(([ \t]*((\/\/)|#)[^\r\n]*)\r?\n)*)
    // )
    // ( [^;\{\/#]+ )

    // Private Property: grabberCommentIndex
    // Identify the PCRE match index for comment body.
    private $grabberCommentIndex = 1;

    // Private Property: grabberCodeIndex
    // Identify the PCRE match index for code line.
    private $grabberCodeIndex = 18;



    // Private Property: source
    // This instance's source array.
    private $source;

    // Private Property: intro
    // First block extracted from source array.
    private $intro;

    // Private Property: path
    // Path to the document tree on disk, or false.
    private $path;

    // Private Property: cwd
    // Directory of the current document on disk, or false.
    private $cwd;

    // Private Property: URL
    // Base URL to reach this NDlite instance, or false.
    private $URL;

    // Private Property: useExt
    // Whether to include the extension of the file.
    private $useExt;

    // Private Property: postfix
    // String to append to URLs.
    private $postfix;

    // Group: Methods

    // Constructor: __construct
    //
    // Creates an instance of the NDlite class.
    //
    // If you supply the optional path and url arguments, references to
    // unknown symbols will cause lookups in the parsed source's current
    // directory, then the root of the document path, for a matching
    // basename.
    //
    // Example:
    //
    // > $doc = new NDlite(
    // >     "/usr/local/src/",
    // >     "/cgi-bin/ndlite.cgi?q=",
    // >     false
    // > );
    //
    // As you can see, it becomes easy to use a standard query if you'd
    // like, although I personally prefer the use of PATH_INFO to make
    // NDlite less apparent to the end-user on a web site. If you're using
    // NDlite for pre-processing, your URL might be simply the base of your
    // site's output directory, like "/api-docs/" for example.
    //
    // Parameters:
    // path - Path to your documentation tree on disk. (Optional.)
    // URL - Base URL to reach this NDline instance. (Optional.)
    // useExt - Whether to include the extension of the file. (Optional.)
    // postfix - String to append to URLs. (Optional.)
    //
    public function __construct($path = false, $URL = false, $useExt = true, $postfix = '')
    {
        $this->path    = $path;
        $this->URL     = $URL;
        $this->useExt  = $useExt;
        $this->postfix = $postfix;
    }

    // Private Method: _stringToLines
    //
    // Convert a string containing a block of comments into an array of
    // lines stripped of their comment indicators.
    //
    // Parameters:
    // comment - The input string.
    //
    // Returns:
    // The array of strings, or false if the block should be discarded.
    //
    private function _stringToLines($comment)
    {
        $result = array();
        if (strncmp($comment, '=begin ', 7) == 0) {
            $tmp = preg_split("/\r?\n/", $comment);
            $len = count($tmp);
            // Skip first line, assuredly '=begin'
            for ($i=1; $i < $len; $i++) {
                if (!preg_match("/^=((end)|(cut))/i", $tmp[$i])) {
                    array_push($result, $tmp[$i]);
                }
            }
        } else {
            preg_match_all("/[ \t]*[\*#\/]+[ \t]?([^\r\n]*)\r?\n/", $comment, $lines, PREG_SET_ORDER);
            foreach ($lines as $line) {
                array_push($result, $line[1]);
            }
        }
        while ((count($result) > 0) && ($result[0] == '')) {
            array_shift($result);
        }

        // Enforce ':' in first line rule OR PHPDoc '/**' introduction
        if ((count($result) > 0) && (strpos($result[0], ':') < 1)) {
            if (preg_match("/^[ \t]*\/\*\*/", $comment)) {
                array_unshift($result, 'PHPDoc');
            } else {
                return false;
            }
        }

        while ((count($result) > 0) && ($result[count($result)-1] == '')) {
            array_pop($result);
        }
        return($result);
    }

    // Private Method: _mkURL
    //
    // Resolve the contents of a reference into a hyperlink.
    // Usually called from the PCRE in <_inlineXHTML()>.
    //
    // Parameters:
    // ref - The reference string.
    // parent - The parent id.
    //
    // Returns:
    // An XHTML anchor ready to display or the original ref if all attempts
    // to resolve failed.
    // 
    private function _mkURL($ref, $parent)
    {
        $result = $ref;

        if (preg_match('/@/', $ref)) {
            // E-mail addresses
            $result = "<a href=\"mailto:$ref\">$ref</a>";

        } elseif (preg_match('/^[^\s:]+:\//i', $ref)) {
            // Full URL
            $result = "<a href=\"$ref\">$ref</a>";

        } elseif (preg_match('/^www\./i', $ref)) {
            // Short URL
            $result = "<a href=\"http://$ref\">$ref</a>";

        } elseif (preg_match('/^([^:\/]+)((::)|[\/\.])([^(]+)(\(\))?$/', $ref, $matches)) {
            // Classed reference
            // Is it in _this_ source?
            $class = $matches[1];
            $id    = $matches[4];
            foreach ($this->source as $block) {
                if (($block['parent'] == $class)  &&  ($block['id'] == $id)) {
                    $result = "<a href=\"#${class}_${id}\">${ref}</a>";
                    break;  // Exact class match assuredly wins.
                }
                // Allow plural forms.
                if (($block['parent'] == $class)  &&  ($block['id'] . 's' == $id)) {
                    $result = "<a href=\"#${class}_${block['id']}\">${ref}</a>";
                    break;  // Exact class match assuredly wins.
                }
            }
            // External file reference.
            // I know, this is only approximative. Hey, we're file-based...
            if (($result == $ref) && ($this->path != '') && ($this->URL != '')) {
                $hits = glob($this->cwd . $class . '.*', GLOB_NOSORT);
                if (count($hits) > 0) {
                    $hit = substr($hits[0], strlen($this->cwd));
                    if (!$this->useExt) {
                        $hit = substr($hit, 0, strrpos($hit, '.'));
                    }
                    $hit .= $this->postfix;
                    $result = "<a href=\"{$hit}#${class}_${id}\">${ref}</a>";
                } else {
                    $hits = glob($this->path . $class . '.*', GLOB_NOSORT);
                    if (count($hits) > 0) {
                        $hit = substr($hits[0], strlen($this->path));
                        if (!$this->useExt) {
                            $hit = substr($hit, 0, strrpos($hit, '.'));
                        }
                        $hit .= $this->postfix;
                        $result = "<a href=\"{$hit}#${class}_${id}\">${ref}</a>";
                    }
                }
            }
        } else {
            preg_match('/^([^()]+)(\(\))?$/', $ref, $matches);
            $id = $matches[1];
            foreach ($this->source as $block) {
                if ($block['id'] == $id) {
                    if ($block['parent'] == $parent) {
                        // Reference found in our same parent. Assuredly best match.
                        $result = "<a href=\"#${parent}_${id}\">${ref}</a>";
                        break;
                    } else {
                        // Possible candidate; take note but continue.
                        $result = "<a href=\"#${block['parent']}_${id}\">${ref}</a>";
                    }
                }
                // Allow plural forms.
                if ($block['id'] . 's' == $id) {
                    if ($block['parent'] == $parent) {
                        // Reference found in our same parent. Assuredly best match.
                        $result = "<a href=\"#${parent}_${block['id']}\">${ref}</a>";
                        break;
                    } else {
                        // Possible candidate; take note but continue.
                        $result = "<a href=\"#${block['parent']}_${id}\">${ref}</a>";
                    }
                }
            }
            // External file reference.
            // I know, this is only approximative. Hey, we're file-based...
            // Mostly copied from the case above, but without a target.
            if (($result == $ref) && ($this->path != '') && ($this->URL != '')) {
                $hits = glob($this->cwd . $id . '.*', GLOB_NOSORT);
                if (count($hits) > 0) {
                    $hit = substr($hits[0], strlen($this->cwd));
                    if (!$this->useExt) {
                        $hit = substr($hit, 0, strrpos($hit, '.'));
                    }
                    $hit .= $this->postfix;
                    $result = "<a href=\"{$hit}\">${ref}</a>";
                } else {
                    $hits = glob($this->path . $id . '.*', GLOB_NOSORT);
                    if (count($hits) > 0) {
                        $hit = substr($hits[0], strlen($this->path));
                        if (!$this->useExt) {
                            $hit = substr($hit, 0, strrpos($hit, '.'));
                        }
                        $hit .= $this->postfix;
                        $result = "<a href=\"{$hit}\">${ref}</a>";
                    }
                }
            }
        }
        if ($result == $ref) {
            $result = "&lt;$ref&gt;";
        }
        return($result);
    }

    // Private Method: _inlineXHTML
    //
    // *Bold*, _emphasis_, English "quotes" and hyperlinks.
    // Usually called from <_linesToXHTML()>.
    //
    // Note that bold only works for up to 40 characters long. This is to
    // try to avoid cases where two stand-alone symbols are present.
    // Emphasis is even more strict, and works only for single words.
    // (Unless underscores are used between the words.)
    //
    // Parameters:
    // line - The string to parse.
    // parent - The parent id for URL construction purposes. (Optional.)
    //
    // Returns:
    // The parsed string.
    //
    private function _inlineXHTML($line, $parent = '')
    {
        $needles = array(
            '/<([^>]+)>/e',  // 1a
            //'/([a-z0-9_\.\-\+]+@[a-z0-9_\-]+\.[a-z0-9_\-\.]+)/i',  // 1b
            '/(^|[^="])"([^">]+)"([^>]|$)/',  // 2
            '/(^|[\s;\'"\(])\*([^\*]{1,40})\*([\s.,;:!\'"\?\)]|$)/',  // 3
            '/(^|[\s;\'"\(])[_\/]([^\s]{1,40})[_\/]([\s.,;:!\'"\?\)]|$)/e',  // 4
        );
        $replacements = array(
            '$this->_mkURL(\'\\1\', $parent)',  // 1a. Hyperlinks
            //'<a href="mailto:$1">$1</a>',  // 1b. Lone e-mail addresses
            '$1&ldquo;$2&rdquo;$3',  // 2. English quotes, avoid XML
            '$1<strong>$2</strong>$3',  // 3. Bold
            "strtr('\\1<em>','\\\\\','').strtr(strtr('\\2','\\\\\',''),'_',' ').strtr('</em>\\3','\\\\\','')",  // 4. Emphasis
        );
        return(preg_replace($needles, $replacements, $line));
    }

    // Private Method: _linesToXHTML
    //
    // Process an array of lines into an XHTML string.
    // Usually called from <toXHTML()>.
    //
    // Note that h4 headers can be multiline, but they cannot exceed 40
    // characters overall. This helps avoid making single-sentence
    // paragraphs which happen to end with ':' into headers.
    //
    // Parameters:
    // lines - Array of strings.
    // parent - Parent id for URL construction purposes. (Optional.)
    //
    // Returns:
    // String representing the XHTML.
    //
    private function _linesToXHTML($lines, $parent = '')
    {
        $result = '';

        // Operating mode before the new line to process:
        // 0  = Out of everything, neutral.
        // 10 = In a temporarily-unknown block.
        // 21 = In a DL and DD block.
        // 31 = In a UL and LI block.
        // 40 = In a code block, block syntax.
        // 41 = In a code block, line syntax.
        $mode = 0;
        $tmp = '';
        $is_dtdd = "/^[ \t]*(([^ \t]+[ \t]+){1,2})-[ \t]+(.*)$/";
        $is_ulli = "/^[ \t]*[-*o+][ \t]+(.*)$/";
        $is_code = "/^[ \t]*[>|:]([ \t](.*))?$/";
        $begins_code = "/^\(((start)|(begin))(\s+((code)|(sample)|(example)|(diagram)|(table)))?\)$/i";
        $ends_code = "/^\(((end)|(finish)|(done)|(stop))(\s+((code)|(sample)|(example)|(diagram)|(table)))?\)$/i";
        if (is_array($lines)) {
            foreach ($lines as $inline) {
                $line = trim($inline);
                // echo "DEBUG: mode $mode parsing: $line\n";
                switch ($mode) {
                case 0 :
                    if ($line != '') {
                        if (preg_match($is_dtdd, $line, $matches)) {
                            $result .= "<dl>\n\t<dt>" . $this->_inlineXHTML($matches[1], $parent) . "</dt>\n\t<dd>";
                            $tmp = $matches[3];
                            $mode = 21;
                        } elseif (preg_match($is_ulli, $line, $matches)) {
                            $result .= "<ul>\n\t<li>";
                            $tmp = $matches[1];
                            $mode = 31;
                        } elseif (preg_match($is_code, $line, $matches)) {
                            $result .= "<pre>" . htmlspecialchars($matches[2], ENT_NOQUOTES) . "\n";
                            $mode = 41;
                        } elseif (preg_match($begins_code, $line)) {
                            $result .= "<pre>";
                            $mode = 40;
                        } else {
                            $len = strlen($line);
                            if (($len < 40) && ($line[$len - 1] == ':') && (!strpos($line, '.'))) {
                                $result .= "\n<h4>" . htmlspecialchars(substr($line, 0, strlen($line) - 1), ENT_NOQUOTES) . "</h4>\n";
                            } else {
                                $tmp .= $line . ' ';
                                $mode = 10;
                            }
                        }
                    }
                    break;
                case 10 :
                    if ($line != '') {
                        $tmp .= $line . ' ';
                    } else {
                        $tmp = rtrim($tmp);
                        $len = strlen($tmp);
                        if (($len < 40) && ($tmp[strlen($tmp) - 1] == ':') && (!strpos($tmp, '.'))) {
                            $result .= "\n<h4>" . htmlspecialchars(substr($tmp, 0, strlen($tmp) - 1), ENT_NOQUOTES) . "</h4>\n";
                        } else {
                            $result .= "<p>" . $this->_inlineXHTML($tmp, $parent) . "</p>\n";
                        }
                        $tmp = '';
                        $mode = 0;
                    }
                    break;
                case 21 :
                    if ($line != '') {
                        if (preg_match($is_dtdd, $line, $matches)) {
                            $result .= $this->_inlineXHTML($tmp, $parent) . "</dd>\n\n\t<dt>" . $this->_inlineXHTML($matches[1], $parent) . "</dt>\n\t<dd>";
                            $tmp = $matches[3];
                        } else {
                            $tmp .= ' ' . $line;
                        }
                    } else {
                        $result .= $this->_inlineXHTML($tmp, $parent) . "</dd>\n\n</dl>\n";
                        $tmp = '';
                        $mode = 0;
                    }
                    break;
                case 31 :
                    if ($line != '') {
                        if (preg_match($is_ulli, $line, $matches)) {
                            $result .= $this->_inlineXHTML($tmp, $parent) . "</li>\n\t<li>";
                            $tmp = $matches[1];
                        } else {
                            $tmp .= ' ' . $line;
                        }
                    } else {
                        $result .= $this->_inlineXHTML($tmp, $parent) . "</li>\n</ul>\n";
                        $tmp = '';
                        $mode = 0;
                    }
                    break;
                case 40 :
                    // Separate mode to allow empty lines in code blocks.
                    if (preg_match($ends_code, $line)) {
                        $result .= "</pre>\n";
                        $mode = 0;
                    } else {
                        $result .= htmlspecialchars($inline, ENT_NOQUOTES) . "\n";
                    }
                    break;
                case 41 :
                    if ($line != '') {
                        if (preg_match($is_code, $line, $matches)) {
                            $result .= htmlspecialchars($matches[2], ENT_NOQUOTES) . "\n";
                        } else {
                            // WARNING: Unspecified behavior!
                            // We're a non-empty line following a code line.
                            // Handling as code.
                            $result .= htmlspecialchars($inline, ENT_NOQUOTES) . "\n";
                        }
                    } else {
                        $result .= "</pre>\n";
                        $mode = 0;
                    }
                    break;
                default :
                    // This is never reached.
                    break;
                }
            }
        }
        // Don't forget to close anything left hanging.
        switch ($mode) {
        case 10 :
            if ($tmp != '') {
                $result .= "<p>" . $this->_inlineXHTML($tmp, $parent) . "</p>\n";
            }
            break;
        case 21 :
            $result .= $this->_inlineXHTML($tmp, $parent) . "</dd>\n\n</dl>\n";
            break;
        case 31 :
            $result .= $this->_inlineXHTML($tmp, $parent) . "</li>\n</ul>\n";
            break;
        case 40 : case 41 :
                $result .= "</pre>\n";
            break;
        default :
            // 0 or unknown = do nothing
            break;
        }

        return($result);
    }

    // Method: parseFile
    //
    // Load a file from disk into this instance. Internally, this is a
    // wrapper around <parseString()> which calls PHP's file_get_contents()
    // for you.
    //
    // Parameters:
    // filename - The file path to read.
    //
    // Returns:
    // The result of <parseString()>.
    //
    public function parseFile($filename)
    {
        $input = false;
        if (file_exists($filename)) {
            $input = file_get_contents($filename);
        }
        $this->cwd = dirname($filename) . '/';
        return($this->parseString($input));
    }

    // Method: parseString
    //
    // Load a source in the form of a string, into this instance.
    //
    // Parameters:
    // input - The string containing source code to parse for comments.
    //
    // Returns:
    // Always true as of this version.
    //
    public function parseString($input)
    {
        $NDlite_kwd_class = '(class)|(structure)|(struct)|(package)|(namespace)|(interface)|(object)';
        $NDlite_kwd_group = '(title)|(group)|(section)|(class)|(structure)|(struct)|(package)|(namespace)|(interface)|(file)|(object)';
        $NDlite_kwd_child = '(property)|(method)|(callback)|(constructor)|(destructor)';
        $NDlite_kwd_generic = '(function)|(procedure)|(routine)|(subroutine)|(constant)|(type)|(typedef)|(macro)|(define)|(variable)|(var)|(array)|(hash)|(string)|(handle)|(pointer)|(reference)|(topic)|(subtitle)';
        $structure = array();
        $idxComment = 0;
        $idxCode = 1;
        $starter = "/^[ \t\r\n]*(private[ \t]+)?(${NDlite_kwd_group}|${NDlite_kwd_child}|${NDlite_kwd_generic}):[ \t][^\r\n]+/i";
        if (preg_match($starter, $input)) {
            // Text file detected. Crawl line by line to split into blocks.
            $lines = preg_split("/\r?\n/", $input);
            $current = array();
            foreach ($lines as $line) {
                if (preg_match($starter, $line)) {
                    while ((count($current) > 0) && ($current[0] == '')) {
                        array_shift($current);
                    }
                    while ((count($current) > 0) && ($current[count($current) - 1] == '')) {
                        array_pop($current);
                    }
                    if (count($current) > 0) {
                        array_push($structure, array($current, ''));
                    }
                    $current = array($line);
                } else {
                    array_push($current, $line);
                }
            }
            while ((count($current) > 0) && ($current[0] == '')) {
                array_shift($current);
            }
            while ((count($current) > 0) && ($current[count($current) - 1] == '')) {
                array_pop($current);
            }
            if (count($current) > 0) {
                array_push($structure, array($current, ''));
            }
        } else {
            preg_match_all($this->grabber, $input, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                array_push($structure, array($this->_stringToLines($match[$this->grabberCommentIndex]), $match[$this->grabberCodeIndex]));
            }
        }
        // echo "<h2>Structure:</h2><pre>"; print_r($structure); echo "</pre>\n";
        // exit(1);
        $this->source = array();
        $lastClass = '';
        foreach ($structure as $block) {
            if ($next = $block[$idxComment]) {
                // Parse special first line
                // CAUTION: Not using our NDlite_kwd regex here: we're at the
                // first line of a comment block, which must start in this
                // format. If something goes wrong, we fall in $valid=false
                // safely.
                $heading = $next[0];
                preg_match("/[ \t]*((private)[ \t]?)?([^ :]+):[ \t]*([^ \t].*)/i", $next[0], $headers);
                array_shift($next);
                while ((count($next) > 0) && ($next[0] == '')) {
                    array_shift($next);
                }
                $private = false;
                if (isset($headers[2]) && ($headers[2] == 'Private' || $headers[2] == 'private')) {
                    $private = true;
                }
                $parent = '';
                $valid = true;
                $kwd_type = NDLITE_K_GENERIC;
                // CAUTION: Using class, not group, to determine parents.
                if (isset($headers[3]) && preg_match("/^(${NDlite_kwd_class})\$/i", $headers[3])) {
                    $lastClass = isset($headers[4]) ? htmlspecialchars($headers[4]) : '';
                    $kwd_type = NDLITE_K_GROUP;
                } elseif (isset($headers[3]) && preg_match("/^(${NDlite_kwd_group})\$/i", $headers[3])) {
                    $parent = $lastClass;
                    $kwd_type = NDLITE_K_GROUP;
                } elseif (isset($headers[3]) && preg_match("/^(${NDlite_kwd_child})\$/i", $headers[3])) {
                    $parent = $lastClass;
                    $kwd_type = NDLITE_K_CHILD;
                } elseif (!isset($headers[3]) || !preg_match("/^(${NDlite_kwd_generic})\$/i", $headers[3])) {
                    $valid = false;
                    // echo "DEBUG IGNORING: '${headers[3]}'\n";
                }
                if ($valid) {
                    array_push(
                        $this->source, array(
                            'private' => $private,
                            'type' => isset($headers[3]) ? $headers[3] : '',
                            'NDtype' => $kwd_type,
                            'id' => isset($headers[4]) ? htmlspecialchars($headers[4]) : '',
                            'parent' => $parent,
                            'lines' => $next,
                            'code' => trim($block[$idxCode])
                        )
                    );
                } else {
                    // Let's see if this is PHPDoc, then
                    if ($heading == 'PHPDoc') {
                        $candidate = Array();
                        $candidate['code'] = trim($block[$idxCode]);
                        $candidate['private'] = (bool) preg_match("/^((private)|(protected))[ \t]+/i", $candidate['code']);
                        $candidate['type'] = 'PHPDoc';
                        $candidate['NDtype'] = NDLITE_K_GENERIC;
                        $candidate['id'] = 'unknown';
                        if (preg_match("/^(abstract[ \t]+)?class[ \t]+([a-zA-Z0-9_]+)[ \t]*/i", $candidate['code'], $classHits)) {
                            $lastClass = $candidate['id'] = $classHits[2];
                            $candidate['NDtype'] = NDLITE_K_GROUP;
                        } else {
                            $parent = $lastClass;
                            if (preg_match("/[^({]*(var|function)[ \t]+(\\$?[a-zA-Z_]+)[;( \t]*/i", $candidate['code'], $identifier)) {
                                $candidate['id'] = $identifier[2];
                            }
                        }
                        $candidate['parent'] = $parent;
                        $candidate['lines'] = Array();
                        $inParameters = false;
                        foreach ($next as $line) {
                            if (preg_match("/^[ \t]*@([a-z]+)[ \t]+(.*)$/", $line, $hits)) {
                                switch($hits[1]) {
                                case 'return':
                                    $candidate['lines'][] = '';
                                    $candidate['lines'][] = 'Returns:';
                                    $candidate['lines'][] = $hits[2];
                                    break;
                                case 'param':
                                    if (!$inParameters) {
                                        $candidate['lines'][] = 'Parameters:';
                                        $inParameters = true;
                                    }
                                    preg_match("/^[ \t]*([a-zA-Z|]+[ \t]+)(&?\\$[a-zA-Z0-9_]+[ \t]+)?(.*)$/", $hits[2], $param);
                                    $candidate['lines'][] = "{$param[2]} _". trim($param[1]) . "_ - {$param[3]}";
                                    break;
                                case 'link':
                                    $candidate['lines'][] = '';
                                    $candidate['lines'][] = ucfirst($hits[1]) . ': <' . $hits[2] . '>';
                                    break;
                                default:
                                    $candidate['lines'][] = '';
                                    $candidate['lines'][] = ucfirst($hits[1]) . ': ' . $hits[2];
                                }
                            } else {
                                $candidate['lines'][] = $line;
                            }
                        }
                        $this->source[] = $candidate;
                    }
                }
            }
        }
        // First block gets special treatment.
        $this->intro = array_shift($this->source);
        return(true);
    }

    // Method: guessTitle
    //
    // Guess the title of this instance. Only useful after parsing some
    // source.
    //
    // Parameters:
    // fallback - Alternative if no suitable candidate is found. (Optional.)
    //
    // Returns:
    // The first of "File", "Title", "Class" or "Group" title found, or your
    // fallback if none is found, or false if no fallback is provided.
    //
    public function guessTitle($fallback = false)
    {
        $result = $fallback;

        if ($this->intro['NDtype'] == NDLITE_K_GROUP) {
            $result = $this->intro['id'];
        } else {
            foreach ($this->source as $block) {
                if ($block['NDtype'] == NDLITE_K_GROUP) { 
                        $result = $block['id'];
                        break;
                }
            }
        }
        return($result);
    }

    // Private Method: _linesToAbstract
    //
    // Extract the first sentence or paragraph from an array of lines. Used
    // for summary in <toXHTML()>.
    //
    // Parameters:
    // lines - Array of strings.
    // parent - Parent id for URL constructino purposes. (Optional.)
    //
    // Returns:
    // The summary string.
    //
    private function _linesToAbstract($lines, $parent = '')
    {
        $result = '';

        foreach ($lines as $line) {
            if (trim($line) == '') {
                break;
            }
            if (preg_match('/^(.*?[.;!?]+)([ \t]|$)/', $line, $matches)) {
                $result .= $matches[1];
                break;
            } else {
                $result .= $line . ' ';
            }
        }
        return($this->_inlineXHTML(rtrim($result), $parent));
    }

    // Method: toXHTML
    //
    // Produce XHTML documentation from the current source. It is up to your
    // application to jazz it up with CSS as you'd like.
    //
    // Parameters:
    // flags - Named array of options. (Optional.)
    //
    // Valid flags:
    // private - Set true to include private topics, false or omit to limit
    //           display to regular topics only.
    // summary - Set false to explicitly request omitting the summary.
    //
    // Returns:
    // The XHTML string ready to display or save.
    //
    public function toXHTML($flags = false)
    {
        $result = '';
        $flag_private = false;
        $flag_summary = true;
        if (is_array($flags)) {
            if (array_key_exists('private', $flags)) {
                $flag_private = $flags['private'];
            }
            if (array_key_exists('summary', $flags)) {
                $flag_summary = $flags['summary'];
            }
        }

        // Introduction
        // if ($this->intro['code'] != '') {
        //     $result .= "<code>" . $this->intro['code'] . "</code>\n\n";
        // };
        $result .= $this->_linesToXHTML($this->intro['lines'], $this->intro['parent']);

        // Table of contents
        if ($flag_summary) {
            $result .= "\n<h2>Summary</h2>\n\n<ul class=\"NDlite_Summary\">\n";
            $ingroup = false;
            $odd = true;
            foreach ($this->source as $block) {
                if ($block['private'] && !$flag_private) {
                    continue;
                }
                if ($block['NDtype'] == NDLITE_K_GROUP) {
                    if ($ingroup) {
                        $result .= "\t\t</ul>\n\t</li>\n";
                    } else {
                        $ingroup = true;
                    }
                    $odd = true;  // Reset zebra hint
                    $result .= "\t<li class=\"NDlite_Group\"><a href=\"#${block['parent']}_${block['id']}\">${block['id']}</a> <span class=\"NDlite_Abstract\">". $this->_linesToAbstract($block['lines'], $block['parent']) ."</span>\n\t\t<ul>\n";
                } else {
                    $result .= ($ingroup ? "\t" : '') . "\t<li". ($odd ? ' class="odd"' : '') ."><a href=\"#${block['parent']}_${block['id']}\">${block['id']}</a> <span class=\"NDlite_Abstract\">". $this->_linesToAbstract($block['lines'], $block['parent']) ."</span></li>\n";
                    $odd = !$odd;
                }
            }
            if ($ingroup) {
                $result .= "\t</ul></li>\n";
            }
            $result .= "</ul>\n";
        }

        // Actual contents
        foreach ($this->source as $block) {
            if ($block['private'] && !$flag_private) {
                continue;
            }
            if ($block['NDtype'] == NDLITE_K_GROUP) {
                $result .= "\n<h2><a id=\"${block['parent']}_${block['id']}\"></a>${block['id']}</h2>\n\n";
            } else {
                $result .= "\n<h3><a id=\"${block['parent']}_${block['id']}\"></a>${block['id']}</h3>\n\n";
                if ($block['code'] != '') {
                    $result .= "<code>" . $block['code'] . "</code>\n\n";
                }
            }
            $result .= $this->_linesToXHTML($block['lines'], $block['parent']);
        }

        return($result);
    }

}

?>
