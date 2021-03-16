<?php
/**
 * @author     Ni Irrty <niirrty+code@gmail.com>
 * @copyright  ©2017, Ni Irrty
 * @package    Niirrty\Md2Pdf
 * @since      2017-10-11
 * @version    0.3.0
 */

declare( strict_types=1 );


namespace Niirrty\Md2Pdf\Commands;


use \GeSHi;
use \Masterminds\HTML5 as HTML5Parser;
use \Michelf\MarkdownExtra;
use \Mpdf\Mpdf;
use Niirrty\IO\Path;
use \QueryPath\DOMQuery;
use \Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use \Niirrty\Md2Pdf\Md2PdfApp;
use \Niirrty\Md2Pdf\TOC\TocGenerator;
use \Mpdf\Output\Destination;


class Build extends Command
{


   // <editor-fold desc="// – – –   P R I V A T E   F I E L D S   – – – – – – – – – – – – – – – – – – – – – – – –">

   /**
    * The absolute path of the required configuration file
    *
    * @var string
    */
   private $_configFile;

   /**
    * The generator config as associative array.
    *
    * The following values must be defined:
    *
    * - 'outputFile' (string): The output PDF file path (relative|absolute path)
    * - 'recursive'  (bool)  : Also include markdown files from sub directories?
    *
    * and the following values can be defined optionally:
    *
    * - 'files'          (array) : Numeric indicated array with all markdown files that should be parsed.
    *                              If not defined, all *.md files are parsed, found inside working dir.
    * - 'documentTitle'  (string): The PDF file document title text.
    * - 'pageTitle'      (string): The Title text that should be displayed inside each page header (if used)
    * - 'header'         (string): The page header template file path. If not defined, no page header is used.
    * - 'footer'         (string): The page footer template file path. If not defined, no page footer is used.
    * - 'userCSS'        (array) : The user defined CSS stylesheet files, If not defined, the internal CSS styles
    *                              are used.
    * - 'tocMaxLevel'    (int)   :
    * - 'pageMarginTopIfHeader' (int):
    *
    * @var array
    */
   private $_config;

   /**
    * @var \Symfony\Component\Console\Output\OutputInterface
    */
   private $_output;

   /**
    * @var \Symfony\Component\Console\Input\InputInterface
    */
   private $_input;

   /**
    * @var array
    */
   private $_htmlStack;

   /**
    * @var \Michelf\MarkdownExtra
    */
   private $_mdParser;

   /**
    * @var \Masterminds\HTML5
    */
   private $_html5Parser;

   /**
    * @var array
    */
   private $_toc;

   private $_geshiStyles;

   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   C O N S T R U C T O R   – – – – – – – – – – – – – – – – – – – –">

   /**
    * Build constructor.
    */
   public function __construct()
   {

      parent::__construct( 'build' );

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P R O T E C T E D   M E T H O D S   – – – – – – – – – – – – – – – – – – – – –">

   protected function configure()
   {

      $this
         ->setName( 'build' )
         ->setDescription( 'Builds the PDF file from specified config JSON file.' )
         ->addOption(
            'dump-html',
            'd',
            InputOption::VALUE_NONE,
            'Dump converted HTML to output for debugging reasons',
            null
         )
         ->addOption(
            'config-file',
            null,
            InputOption::VALUE_REQUIRED,
            'Use config file with defined name.',
            null
         );

   }

   protected function execute( InputInterface $input, OutputInterface $output )
   {

      // Remember the input reader and output writer
      $this->_input  = $input;
      $this->_output = $output;
      $normalizedCwd = \str_replace( '\\', '/', \getcwd() );

      $style = new OutputFormatterStyle( 'red', 'yellow' );
      $this->_output->getFormatter()->setStyle( 'fire', $style );

      // Define the path of the config file that should be used.
      $this->_findConfigFilePath();

      // Read the config and check if its valid
      if ( ! $this->_readConfig() )
      {
         return 1;
      }

      // Get MD files if none are already defined by config.
      $this->_findMdFilesByNeed();

      // Init parser and helpers
      $this->_mdParser    = new MarkdownExtra();
      $this->_html5Parser = new HTML5Parser();
      $this->_toc         = [];
      $this->_geshiStyles = [];

      $verbose = $input->hasOption( 'verbose' ) && false !== $input->getOption( 'verbose' );
      $processed = 0;

      // Loop all MD files
      foreach ( $this->_config[ 'files' ] as $mdFile )
      {
         if ( $verbose ) { $this->_writeln( [ "  + Processing markdown file '$mdFile'" ] ); }
         // Convert each markdown file to a html string and store it inside $this->_htmlStack
         $this->_mdToHtml( $mdFile, $normalizedCwd );
         $processed++;
      }

      $this->_writeln( [ "  + <comment>$processed</comment> markdown files successful processed to intermediate HTML" ] );

      // Read the HTML structure
      $html = \file_get_contents( \dirname( \dirname( __DIR__ ) ) . '/app/index.html' );

      $inlineStyles =
         \file_get_contents( \dirname( \dirname( __DIR__ ) ) . '/app/default.css' ) .
         (
            \count( $this->_geshiStyles ) > 0
               ? ( "\n" . \implode( "\n/**/\n", \array_values( $this->_geshiStyles) ) )
               : ''
         );

      $search  = [ '{CSS}', '{LANG}', '{TITLE}' ];
      $replace = [
         $inlineStyles,
         'en',
         empty( $this->_config[ 'documentTitle' ] ) ? 'Documentation' : $this->_config[ 'documentTitle' ]
      ];

      if ( 0 < \count( $this->_config[ 'userCSS' ] ) )
      {
         $search[] = '{USER-CSS}';
         for ( $i = 0, $c = \count( $this->_config[ 'userCSS' ] ); $i < $c; $i++ )
         {
            $this->_config[ 'userCSS' ][ $i ] = \str_replace( '{CWD}', \getcwd(), $this->_config[ 'userCSS' ][ $i ] );
            if ( ! \preg_match( '~^(/|[a-z]:)~i', $this->_config[ 'userCSS' ][ $i ] ) )
            {
               $this->_config[ 'userCSS' ][ $i ] = \ltrim( \realpath( $this->_config[ 'userCSS' ][ $i ] ), '/' );
            }
            else
            {
               $this->_config[ 'userCSS' ][ $i ] = \ltrim( $this->_config[ 'userCSS' ][ $i ], '/' );
            }
         }
         $replace[] = '<link rel="stylesheet" href="file:///'
                      . implode( '"><link rel="stylesheet" href="file:///', $this->_config[ 'userCSS' ] )
                      . '">';
      }
      else
      {
         $search[]  = '{USER-CSS}';
         $replace[] = '';
      }

      // Set all replacements, excluding content
      $html = \str_replace( $search, $replace, $html );

      // Build content and insert it into $html
      $html = \str_replace( '{CONTENT}', \implode( '<div class="pageBreakAfter"></div>', $this->_htmlStack ), $html );

      if ( $verbose ) { $this->_writeln( [ "  + HTML replacements handled…" ] ); }

      $useHeader = ! empty( $this->_config[ 'header' ] );
      $useFooter = ! empty( $this->_config[ 'footer' ] );

      // Define all required PDF options
      $pdfOptions = [
         // Template DIR must be defined because original tmp folder is inside the PHAR archive where writing fails
         'tempDir'         => sys_get_temp_dir() . '/mpdf',
         // The Paper format that should be used
         'format'          => 'A4',
         // Page margins in millimeters
         'margin_left'     => 20,
         'margin_right'    => 20,
         // Use main page margin if no header is used and +5 if a header is used
         'margin_top'      => $useHeader ? $this->_config[ 'pageMarginTopIfHeader' ] : 20,
         'margin_bottom'   => 20,
         'margin_header'   => $this->_config[ 'pageMarginTopIfHeader' ] - 20,
         'margin_footer'   => 0,
         // mdf only supports the 'c' mode if its used inside a PHAR file. All other will trigger exceptions
         'mode'            => 'c'
      ];

      // Init mpdf for PDF creation
      $mpdf = new Mpdf( $pdfOptions );
      if ( ! empty( $this->_config[ 'documentTitle' ] ) )
      {
         $mpdf->SetTitle( $this->_config[ 'documentTitle' ] );
      }

      // Use page header if defined
      if ( $useHeader )
      {
         $headerHTML = \file_get_contents( \str_replace( '{CWD}', \getcwd(), $this->_config[ 'header' ] ) );
         $search     = [ '{CWD}', '{TITLE}' ];
         $replace    = [ \getcwd(), empty( $this->_config[ 'pageTitle' ] ) ? '' : $this->_config[ 'pageTitle' ] ];
         $mpdf->SetHTMLHeader( \str_replace( $search, $replace, $headerHTML ) );
         if ( $verbose ) { $this->_writeln( [ "  + Header HTML inserted…" ] ); }
      }

      // Use page footer if defined
      if ( $useFooter )
      {
         $footerHTML = \file_get_contents( \str_replace( '{CWD}', \getcwd(), $this->_config[ 'footer' ] ) );
         $search  = [ '{CWD}', '{TITLE}' ];
         $replace = [ \getcwd(), empty( $this->_config[ 'pageTitle' ] ) ? '' : $this->_config[ 'pageTitle' ] ];
         $mpdf->SetHTMLFooter( \str_replace( $search, $replace, $footerHTML ) );
         if ( $verbose ) { $this->_writeln( [ "  + Footer HTML inserted…" ] ); }
      }

      // Generate and include TOC if enabled
      if ( $this->_config[ 'tocEnabled' ] )
      {

         $tocGenerator = new TocGenerator();

         #var_dump( $this->_config ); exit;
         #echo 'TOChtml: ', $tocHTML, "\n"; exit;

         $tocHTML = $tocGenerator->getHtmlMenu( $html, 1, $this->_config[ 'tocMaxLevel' ] );
         $html = \preg_replace_callback(
            '~<body>\s+<div id="Page">~',
            function ( $match ) use ( $tocHTML )
            {
               return $match[ 0 ] . "\n<div class='md2pdfToc'>\n<h1>Übersicht</h1>\n" . $tocHTML . "\n</div>";
            },
            $html
         );
         if ( $verbose ) { $this->_writeln( [ "  + TOC inserted…" ] ); }

      }

      $mpdf->WriteHTML( $html );
      $outFile = \str_replace( '{CWD}', \getcwd(), $this->_config[ 'outputFile' ] );

      $mpdf->Output( $outFile, Destination::FILE );

      if ( $input->hasOption( 'dump-html' ) && false !== $input->getOption( 'dump-html' ) )
      {
         echo $html;
      }

      $this->_writeln( 'The output PDF file "<fire>' . $outFile . '</fire>" was successful generated.' );

      return 0;

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P R I V A T E   M E T H O D S   – – – – – – – – – – – – – – – – – – – – – – –">

   private function _findConfigFilePath()
   {

      $userConfigFile = ! $this->_input->hasOption( 'config-file' ) ? '' : $this->_input->getOption( 'config-file' );

      if ( $userConfigFile &&
           '' !== $userConfigFile &&
           '.json' === \substr( $userConfigFile, -5 ) &&
           \file_exists( $userConfigFile ) )
      {
         $this->_configFile = $userConfigFile;
      }
      else
      {
         $this->_configFile = \getcwd() . DIRECTORY_SEPARATOR . 'md2pdf.json';
      }

   }

   private function _findMdFilesByNeed()
   {

      if ( ! isset( $this->_config[ 'files' ] )
         ||
           ! \is_array( $this->_config[ 'files' ] )
         ||
           1 > \count( $this->_config[ 'files' ] ) )
      {
         # No md files defined, get it from current working dir
         $this->_config[ 'files' ] = \array_values( Md2PdfApp::getAllMdFiles( $this->_config[ 'recursive' ] ) );
      }

   }

   private function _readConfig()
   {

      if ( ! \file_exists( $this->_configFile ) )
      {
         $this->_writeln( [
            '  <question>-- ERROR: Please run the init command before you use build!</question>',
            '  <question>          Missing the required `md2pdf.json` configuration file.</question>',
            ''
         ] );
         return false;
      }

      // Read the config from configFile
      $this->_config = @\json_decode( @\file_get_contents( $this->_configFile ), true );

      // Check if the config is usable
      if ( ! \is_array( $this->_config ) || ! isset( $this->_config[ 'outputFile' ] ) )
      {
         $this->_writeln( [
            '  <question>-- ERROR: Invalid `md2pdf.json` configuration file.</question>',
            ''
         ] );
         return false;
      }

      if ( ! isset( $this->_config[ 'recursive' ] ) )
      {
         $this->_config[ 'recursive' ] = false;
      }

      if ( empty( $this->_config[ 'tocMaxLevel' ] ) )
      {
         $this->_config[ 'tocMaxLevel' ] = 4;
      }
      else
      {
         $this->_config[ 'tocMaxLevel' ] = ( int ) $this->_config[ 'tocMaxLevel' ];
      }

      if ( ! isset( $this->_config[ 'tocEnabled' ] ) )
      {
         $this->_config[ 'tocEnabled' ] = 0 < $this->_config[ 'tocMaxLevel' ];
      }

      if ( ! isset( $this->_config[ 'pageMarginTopIfHeader' ] ) )
      {
         $this->_config[ 'pageMarginTopIfHeader' ] = 25;
      }
      else if ( empty( $this->_config[ 'pageMarginTopIfHeader' ] ) )
      {
         $this->_config[ 'pageMarginTopIfHeader' ] = 25;
      }

      return true;

   }

   private function _writeln( $messages )
   {

      $this->_output->writeln( $messages );

   }

   private function _mdToHtml( string $filePath, string $normalizedCwd )
   {

      // Normalize the file path to unix directory separator /
      $filePath = \str_replace( [ '{CWD}', '\\' ], [ \getcwd(), '/' ], $filePath );

      // Read the markdown content
      $markdownContent = \file_get_contents( $filePath );

      $idFilePath      = Path::RemoveWorkingDir( $filePath );
      $pageId          = static::mdFilePathToPageId( $idFilePath );

      // Convert Markdown to HTML
      $html = $this->_mdParser->transform( $markdownContent );

      // Replace <pre><code> by <pre>
      $html = \preg_replace_callback(
         '~(<pre>\s?<code\s+class="([^"]+)">)~i',
         function ( $match ) { return '<pre class="' . $match[ 2 ] . '">'; },
         $html
      );
      $html = \preg_replace( '~<pre>\s?<code>~i', '<pre>', $html );
      $html = \preg_replace( '~</code>\s?</pre>~i', '</pre>', $html );

      // Replace manual page breaks
      $html = \preg_replace( '~<p>\s*!!!?PAGEBREAK!!!?\s*</p>~i', '<div class="pageBreakAfter"></div>', $html );

      // First all h1-h6 elements become an unique ID, to be linkable.
      $html = \preg_replace_callback(
         '~<h([1-6])>([^<\\r\\n]+)</h([1-6])>~',
         function( $matches )
         {
            $text = \trim( $matches[ 2 ] );
            if ( '' === $text || $matches[ 1 ] !== $matches[ 3 ] )
            {
               return $matches[ 0 ];
            }
            $id1 = \strtolower( static::convertStringToWord( $text ) );
            $id2 = $id1;
            $i   = 0;
            while ( \in_array( $id2, $this->_toc ) )
            {
               $id2 = $id1 . ++$i;
            }
            $this->_toc[] = $id2;
            return \sprintf(
               '<h%s><a name="%s">%s</a></h%s>',
               $matches[ 1 ],
               $id2,
               $matches[ 2 ],
               $matches[ 1 ]
            );
         },
         $html
      );

      // Get the DOM of the resulting HTML => Required to manipulate the dom
      $dom = $this->_html5Parser->loadHTML( $html );

      // Next all links should be checked
      $links = \QueryPath::withHTML( $dom, 'a[href]' );
      if ( $links instanceof DOMQuery )
      {
         foreach ( $links as $link )
         {

            $href = \str_replace( '\\', '/', \trim( $link->attr( 'href' ) ) );

            // Ignore empty hrefs
            if ( '' === $href )
            {
               continue;
            }

            // Ignore anchor hrefs
            if ( $href[ 0 ] === '#' )
            {
               continue;
            }

            // Ignore URIs
            if ( \preg_match( '~^(https?://|file:/)~', $href ) )
            {
               continue;
            }

            // Add file:// prefix for absolute unix paths
            if ( $href[ 0 ] === '/' )
            {
               $link->attr( 'href', 'file://' . $href );
               continue;
            }

            // Add file:/// prefix for absolute windows paths
            if ( \preg_match( '~^[a-z]:~i', $href ) )
            {
               $link->attr( 'href', 'file:///' . $href );
               continue;
            }

            $anchorStartPos = \strpos( $href, '#' );
            if ( -1 < $anchorStartPos )
            {
               $link->attr( 'href', \substr( $href, $anchorStartPos ) );
               continue;
            }

            // ../… or ./… paths
            if ( 0 === \strpos( $href, '.' ) )
            {
               if ( false !== ( $abs = @\realpath( $href ) ) )
               {
                  // can resolve the real path
                  $abs = \str_replace( '\\', '/', $abs );
                  if ( \strlen( $abs ) - 3 !== \strpos( $abs, '.md' ) )
                  {
                     $link->attr( 'href', null );
                     continue;
                  }
                  if ( \preg_match( '~^' . \preg_quote( $normalizedCwd ) . '/(.+)$~', $abs, $matches ) )
                  {
                     $link->attr( 'href', '#' . static::mdFilePathToPageId( $href ) );
                     continue;
                  }
               }
               $link->attr( 'href', null );
               continue;
            }

            if ( \preg_match( '~.+\.md$~', $href ) )
            {
               $link->attr( 'href', '#' . static::mdFilePathToPageId( $href ) );
            }

         }
      }

      // Convert PRE blocks with a class that defines a supported GESHI language like class="php"
      $preBlocks = \QueryPath::withHTML( $dom, 'pre' );
      if ( $preBlocks instanceof DOMQuery )
      {

         foreach ( $preBlocks as $preBlock )
         {

            # $codeBlock is of type \QueryPath\DOMQuery();
            if ( ! $preBlock->hasAttr( 'class' ) )
            {
               continue;
            }

            $language = $preBlock->attr( 'class' );
            switch ( $language )
            {
               case 'json':
                  $language = 'javascript';
                  $preBlock->attr( 'class', 'javascript' );
                  break;
               case 'html':
                  $language = 'html5';
                  $preBlock->attr( 'class', 'html5' );
                  break;
               case 'less':
               case 'scss':
                  $language = 'sass';
                  $preBlock->attr( 'class', 'sass' );
                  break;
               default:
                  break;
            }

            $geshi = new GeSHi( $preBlock->text(), $language );
            $geshi->set_header_type( GESHI_HEADER_NONE );
            $geshi->enable_classes();

            if ( ! isset( $this->_geshiStyles[ $language ] ) )
            {
               $this->_geshiStyles[ $language ] = static::fixGeShiStyle( $geshi->get_stylesheet( false ) );
            }

            $preBlock->html( \str_replace( '&nbsp;', ' ', \preg_replace( '~<br ?/?>~', '', $geshi->parse_code() ) ) );

         }

      }

      // Find all img elements and convert URIs and extract optional params width, height, class
      $images = \QueryPath::withHTML( $dom, 'img' );
      if ( $images instanceof DOMQuery )
      {

         foreach ( $images as $image )
         {

            $src = \str_replace( '\\', '/', \trim( $image->attr( 'src' ) ) );

            // Ignore empty hrefs
            if ( '' === $src )
            {
               continue;
            }

            $parts = \explode( '|', $src );
            $src = $parts[ 0 ];
            for ( $i = 1, $c = \count( $parts ); $i < $c; $i++ )
            {
               $keyValuePair = \explode( '=', $parts[ $i ], 2 );
               $key = \strtolower( $keyValuePair[ 0 ] );
               $style = '';
               switch ( $key )
               {
                  case 'width':
                     $image->attr( 'width', \trim( $keyValuePair[ 1 ] ) );
                     break;
                  case 'height':
                     $image->attr( 'height', \trim( $keyValuePair[ 1 ] ) );
                     break;
                  case 'class':
                     $image->attr( 'class', \trim( $keyValuePair[ 1 ] ) );
                     break;
                  default:
                     break;
               }
               if ( '' !== $style )
               {
                  $image->attr( 'style', $style );
               }
               if ( 2 !== \count( $keyValuePair ) )
               {
                  continue;
               }
            }

            // Ignore URIs
            if ( \preg_match( '~^(https?://|file:/)~', $src ) )
            {
               continue;
            }

            // Add file:// prefix for absolute unix paths
            if ( $src[ 0 ] === '/' )
            {
               $src = 'file://' . $src;
            }
            // Add file:/// prefix for absolute windows paths
            else if ( \preg_match( '~^[a-z]:~i', $src ) )
            {
               $src = 'file:///' . $src;
            }
            else
            {

               if ( false !== ( $abs = @\realpath( $src ) ) )
               {
                  // can resolve the real path
                  $src = 'file:///' . \ltrim( \str_replace( '\\', '/', $abs ), '/' );
               }
               else { $src = null; }

            }

            if ( empty( $src ) )
            {
               $image->attr( 'src', '' );
               $image->attr( 'style', 'display:none;' );
            }
            else
            {
               $image->attr( 'src', $src );
               $image->attr( 'style', 'display:inline-block;' );
            }

         }

      }

      $this->_htmlStack[] = static::paginize( $this->_html5Parser->saveHTML( $dom ), $pageId );

   }

   private static function paginize( string $html, string $pageId ) : string
   {

      return '<div id="' . $pageId . '" class="page"><a name="' . $pageId . '"></a>' .
             \preg_replace( '~(<!DOCTYPE html>\s+<html>|</html>)~i', '', $html ) .
             '</div>';

   }

   private static function convertStringToWord( string $string ) : string
   {

      $string = \preg_replace( '~[\s./+\~:-]+~', '_', $string );

      $string = \str_replace(
         [ 'Ä', 'ä', 'Ö', 'ö', 'Ü', 'ü', 'ß', 'ë', 'Ë', 'ê', 'Ê', 'è', 'È', 'é', 'É', 'ž', 'Ž', 'û', 'Û', '€',
           'ù', 'Ù', 'ú', 'Ú', 'ï', 'Ï', 'î', 'Î', 'ì', 'Ì', 'í', 'Í', 'ô', 'Ô', 'ò', 'Ò', 'ó', 'Ó', 'ø', 'Ø',
           'â', 'Â', 'à', 'À', 'á', 'Á', 'æ', 'Æ', 'š', 'Š', '£', 'ý', 'Ý', 'ÿ', 'Ÿ', '¥', 'ç', 'Ç', 'ñ', 'Ñ',
           '–', ' ', '.', ':', '-', '/', '$' ],
         [ 'Ae', 'ae', 'Oe', 'oe', 'Ue', 'ue', 'ss', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'z', 'Z', 'u', 'U', 'EUR',
           'u', 'U', 'u', 'U', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'o', 'O', 'o', 'O', 'o', 'O', 'oe', 'Oe',
           'a', 'A', 'a', 'A', 'a', 'A', 'ae', 'Ae', 's', 'S', 'L', 'y', 'Y', 'y', 'Y', 'Yen', 'c', 'C', 'n', 'N',
           '_', '_', '_', '_', '_', '_', 'S' ],
         $string
      );

      return \preg_replace( '~[^a-zA-Z0-9_]+~', '', $string );

   }

   private static function mdFilePathToPageId( string $filePath ) : string
   {

      return static::convertStringToWord( \substr( $filePath, 0, -3 ) );

   }

   private static function fixGeShiStyle( string $cssString ) : string
   {

      // First split into lines
      $linesSrc = \preg_split( '~(\r\n|\n|\r)~', $cssString );

      // A new lines array is needed because commentLines are deleted
      $linesOut = [];

      // Loop all source lines
      for ( $i = 0, $c = \count( $linesSrc ); $i < $c; $i++ )
      {

         // Remove leading and trailing white spaces
         $lineTrimmed = \trim( $linesSrc[ $i ] );

         if ( '' === $lineTrimmed )
         {
            // ignore empty lines
            continue;
         }

         if ( 0 === \strpos( $lineTrimmed, '/*' ) )
         {
            // The line starts as comment line…
            $idx = \strpos( $lineTrimmed, '*/', 2 );
            if ( 0 > $idx )
            {
               // …and there is no closing */ => Find it
               $j = $i + 1;
               for ( ; $j < $c; $j++ )
               {
                  $idx = \strpos( $linesSrc[ $j ], '*/' );
                  if ( 0 > $idx )
                  {
                     continue;
                  }
                  break;
               }
               $i = $j;
               continue;
            }
            continue;
         }

         if ( 0 !== \strpos( $lineTrimmed, '.' ) )
         {
            // ignore all lines not starting with a dot
            continue;
         }

         // Split current line at { because we need all stuff before the {
         $parts = \explode( '{', $lineTrimmed, 2 );

         // Insert 'pre' before each language class
         $parts[ 0 ] = \preg_replace_callback(
            '~(^\s*\.|,\s*\.)~',
            function( $match ) { return ( '.' === \trim( $match[ 1 ] ) ) ? 'pre.' : ', pre.'; },
            $parts[ 0 ]
         );

         // Rebuild the css rule ant remember it
         $linesOut[] = \implode( '{', $parts );

      }

      return \implode( "\n", $linesOut );

   }

   // </editor-fold>


}

