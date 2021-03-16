<?php
/**
 * @author     Ni Irrty <niirrty+code@gmail.com>
 * @copyright  ©2017, Ni Irrty
 * @package    Niirrty\Md2Pdf\Commands
 * @since      2017-10-11
 * @version    0.3.0
 */

declare( strict_types=1 );


namespace Niirrty\Md2Pdf\Commands;


use \Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Question\Question;
use \Niirrty\Md2Pdf\Md2PdfApp;


class Init extends Command
{


   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionOutputFile;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionInitDefaults;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionScanRecursive;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionMdFile;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionDocTitle;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionPageTitle;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionHeaderTpl;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionFooterTpl;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionUserCSS;

   /**
    * @type \Symfony\Component\Console\Question\Question
    */
   private $_questionTocMaxLevel;

   /**
    * @type \Symfony\Component\Console\Helper\QuestionHelper
    */
   private $_helper;

   /**
    * @type \Symfony\Component\Console\Output\OutputInterface
    */
   private $_output;

   /**
    * @type string
    */
   private $_headerTpl;

   /**
    * @type string
    */
   private $_footerTpl;

   /**
    * @type array
    */
   private $_userCssFiles;


   // <editor-fold desc="// – – –   P U B L I C   C O N S T R U C T O R   – – – – – – – – – – – – – – – – – – – –">

   /**
    * Init constructor.
    */
   public function __construct()
   {

      parent::__construct( 'init' );

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P R O T E C T E D   M E T H O D S   – – – – – – – – – – – – – – – – – – – – –">

   protected function configure()
   {

      $this
         ->setName( 'init' )
         ->setDescription( 'Create interactive a new md2pdf config.' )
         ->addOption( 'config-file', null, InputOption::VALUE_REQUIRED, 'Generate config file with defined name.', '' );

   }

   protected function execute( InputInterface $input, OutputInterface $output )
   {

      $this->_output       = $output;
      $this->_userCssFiles = [];
      $this->_footerTpl    = '';
      $this->_headerTpl    = '';

      $style = new OutputFormatterStyle( 'red', 'yellow' );
      $this->_output->getFormatter()->setStyle( 'fire', $style );

      // Set config file (use user defined config file if available)
      // it can be defined by commandline arg --config-file=…
      $userConfigFile = ! $input->hasOption( 'config-file' ) ? '' : $input->getOption( 'config-file' );
      if ( $userConfigFile && '' !== $userConfigFile && '.json' === \substr( $userConfigFile, -5 ) )
      {
         $configFile = $userConfigFile;
      }
      else
      {
         $configFile = \getcwd() . DIRECTORY_SEPARATOR . 'md2pdf.json';
      }

      // Write initial text to STDOUT
      $this->_output->writeln( [
         'Niirrty MarkDown to PDF converter',
         'Insert the data to create a new md2pdf config in current working dir.',
         'The resulting config file is "' . $configFile . '"',
         ''
      ] );

      // Init the question helper and all required config questions
      $this->_initQuestions();

      // Here the config data are stored.
      $jsonData = [];

      // Init with default by need
      $initWithDefaults =
         'y' === \strtolower( \trim( $this->_helper->ask( $input, $output, $this->_questionInitDefaults ) ) );
      if ( $initWithDefaults ) { $this->_initWithDefaults(); }

      // Get output PDF file from user input
      $jsonData[ 'outputFile' ] = $this->_getOutputPdfFile( $input );

      // Get if recursive scanning for md files should be used
      $jsonData[ 'recursive'  ] =
         'y' === \strtolower( \trim( $this->_helper->ask( $input, $output, $this->_questionScanRecursive ) ) );

      // Get all MD files inside CWD recursive for auto completion
      $files = \array_values( Md2PdfApp::getAllMdFiles( true ) );
      // And use them as auto complete values
      $this->_questionMdFile->setAutocompleterValues( $files );

      // Get optionally specific markdown files that should be converted exclusive
      $jsonData[ 'files' ] = $this->_getMdFiles( $input );

      // Get the document title
      $docTitle = \trim( $this->_helper->ask( $input, $output, $this->_questionDocTitle ) );
      if ( '' !== $docTitle ) { $jsonData[ 'documentTitle' ] = $docTitle; }

      // Get the page title
      $pageTitle = \trim( $this->_helper->ask( $input, $output, $this->_questionPageTitle ) );
      if ( '' !== $pageTitle ) { $jsonData[ 'pageTitle' ] = $pageTitle; }

      // Get Header template
      $this->_getHeaderTplFile( $input );
      if ( '' !== $this->_headerTpl ) { $jsonData[ 'header' ] = $this->_headerTpl; }

      // Get Footer template
      $this->_getFooterTplFile( $input );
      if ( '' !== $this->_footerTpl ) { $jsonData[ 'footer' ] = $this->_footerTpl; }

      $this->_getUserCssFiles( $input );
      if ( 0 < \count( $this->_userCssFiles ) ) { $jsonData[ 'userCSS' ] = $this->_userCssFiles; }

      $maxTocLevel = (int) \trim( $this->_helper->ask( $input, $output, $this->_questionTocMaxLevel ) );

      $jsonData[ 'tocEnabled' ]  = ( $maxTocLevel > 0 && $maxTocLevel < 7 );
      $jsonData[ 'tocMaxLevel' ] = $maxTocLevel;
      $jsonData[ 'pageMarginTopIfHeader' ] = 25;

      \file_put_contents( $configFile, \json_encode( $jsonData, \JSON_PRETTY_PRINT ) );

      $output->writeln( '' );
      $output->writeln( '+ <info>Configuration successful generated…</info>' );
      $output->writeln( '  Now you can call the "build" command to build the PDF file.' );
      $output->writeln( '' );

      return 0;

   }

   // </editor-fold>


   private function _initQuestions()
   {

      // The helper is required to easier handle user input
      $this->_helper = $this->getHelper( 'question' );

      // Define all questions for user interaction
      $this->_questionOutputFile = new Question( '> Output PDF file []                : ', '' );
      $this->_questionInitDefaults = new Question( '> Init with defaults? [y]           : ', 'y' );
      #$uofQuestion = new Question( '> Use own fonts? [y|N]              : ', 'n' );
      $this->_questionScanRecursive = new Question( '> Scan recursive for MD files? [n]  : ', 'n' );
      $this->_questionMdFile = new Question( '> Markdown file (optionally) []     : ', '' );
      $this->_questionDocTitle = new Question( '> Document title []                 : ', '' );
      #$autQuestion = new Question( '> Document author name []           : ', '' );
      $this->_questionPageTitle = new Question( '> Page title []                     : ', '' );
      $this->_questionHeaderTpl = new Question( '> Document header template file []  : ', '' );
      $this->_questionFooterTpl = new Question( '> Document footer template file []  : ', '' );
      $this->_questionUserCSS = new Question( '> User CSS file []                  : ', '' );
      $this->_questionTocMaxLevel = new Question( '> TOC max level (0=no TOC) [0]      : ', '0' );

   }

   private function _initWithDefaults()
   {

      // Here all default files get stored
      $baseFolder   = \getcwd() . '/.md2pdf';

      // Copy default from here
      $sourceFolder = \dirname( \dirname( __DIR__ ) ) . '/app/.md2pdf';

      // Create bese folder if it not exists
      if ( ! \is_dir( $baseFolder ) )
      {
         \mkdir( $baseFolder, 0775 );
         $this->_output->writeln( "  + <info>Base folder '$baseFolder' created</info>" );
      }

      // Copy header template if it exists at source
      $srcFile = $sourceFolder . '/header.tpl';
      if ( \file_exists( $srcFile ) )
      {
         \file_put_contents( $baseFolder . '/header.tpl', \file_get_contents( $srcFile ) );
         $this->_headerTpl = '{CWD}/.md2pdf/header.tpl';
         $this->_output->writeln( "  + <info>Header template '{$baseFolder}/header.tpl' created</info>" );
      }

      // base CSS file
      $srcFile = $sourceFolder . '/md2pdf.css';
      if ( \file_exists( $srcFile ) && ! \file_exists( $baseFolder . '/md2pdf.css' ) )
      {
         \file_put_contents( $baseFolder . '/md2pdf.css', \file_get_contents( $srcFile ) );
         $this->_output->writeln( "  + <info>User CSS file '{$baseFolder}/uk-md2pdf.css' created</info>" );
      }
      $this->_userCssFiles[] = '{CWD}/.md2pdf/md2pdf.css';

      // Copy footer template if it exists
      if ( \file_exists( $sourceFolder . '/footer.tpl' ) && ! \file_exists( $baseFolder . '/footer.tpl' ) )
      {
         \file_put_contents( $baseFolder . '/footer.tpl', \file_get_contents( $sourceFolder . '/footer.tpl' ) );
         $this->_footerTpl = '{CWD}/.md2pdf/footer.tpl';
         $this->_output->writeln( "  + <info>Footer template '{$baseFolder}/footer.tpl' created</info>" );
      }

      // Copy the logo image
      if ( \file_exists( $sourceFolder . '/logo.png' ) && ! \file_exists( $baseFolder . '/logo.png' ) )
      {
         \file_put_contents( $baseFolder . '/logo.png', \file_get_contents( $sourceFolder . '/logo.png' ) );
         $this->_output->writeln( "  + <info>Default logo image '{$baseFolder}/logo_yf.png' created</info>" );
      }

   }

   private function _getOutputPdfFile( InputInterface $input ) : string
   {

      $outputPdfFile = '';

      while ( '' === $outputPdfFile )
      {

         $outputPdfFile = \trim( $this->_helper->ask( $input, $this->_output, $this->_questionOutputFile ) );

         if ( '' === $outputPdfFile )
         {
            $this->_output->writeln(
               '  <question>-- ERROR: Please define the name of the resulting PDF file! Press [Strg]+[C] for exit</question>'
            );
            $outputPdfFile = '';
         }

         else if ( ! \preg_match( '~.+\.pdf$~', $outputPdfFile ) )
         {
            $this->_output->writeln(
               '  <question>-- ERROR: The file name must end with the .pdf file extension! Press [Strg]+[C] for exit</question>'
            );
            $outputPdfFile = '';
         }

         else
         {
            if ( ! \preg_match( '~^(/|[a-z]:)~i', $outputPdfFile ) && 0 !== \stripos( $outputPdfFile, '{CWD}' ) )
            {
               $outputPdfFile = '{CWD}/' . $outputPdfFile;
            }
         }

      }

      return $outputPdfFile;

   }

   private function _getMdFiles( InputInterface $input ) : array
   {

      // Get all MD files inside CWD recursive for auto completion
      // And use them as auto complete values
      $this->_questionMdFile->setAutocompleterValues( \array_values( Md2PdfApp::getAllMdFiles( true ) ) );

      // Get optionally specific markdown files that should be converted exclusive
      $mdFile  = null;
      $mdFiles = [];
      while ( '' !== $mdFile )
      {

         $mdFile = \trim( $this->_helper->ask( $input, $this->_output, $this->_questionMdFile ) );

         if ( '' === $mdFile ) { continue; }

         if ( ! \file_exists( $mdFile ) )
         {
            $this->_output->writeln( '  <question>-- ERROR: The defined markdown file not exists!</question>' );
            continue;
         }

         if ( ! \preg_match( '~^(/|[a-z]:)~i', $mdFile ) && 0 !== \stripos( $mdFile, '{CWD}' ) )
         {
            $mdFile = '{CWD}/' . $mdFile;
         }

         $mdFiles[] = $mdFile;

      }

      return $mdFiles;

   }

   private function _getHeaderTplFile( InputInterface $input )
   {

      if ( '' !== $this->_headerTpl )
      {
         return;
      }

      $this->_questionHeaderTpl->setAutocompleterValues(
         \array_values( Md2PdfApp::getAllFilesByRegex( '~.+\.tpl$~i', true ) )
      );

      $this->_headerTpl = \trim( $this->_helper->ask( $input, $this->_output, $this->_questionHeaderTpl ) );

      if ( '' !== $this->_headerTpl && file_exists( $this->_headerTpl ) )
      {
         if ( ! \preg_match( '~^(/|[a-z]:)~i', $this->_headerTpl ) && 0 !== \stripos( $this->_headerTpl, '{CWD}' ) )
         {
            $this->_headerTpl = '{CWD}/' . $this->_headerTpl;
         }
      }

   }

   private function _getFooterTplFile( InputInterface $input )
   {

      if ( '' !== $this->_footerTpl )
      {
         return;
      }

      $this->_questionFooterTpl->setAutocompleterValues(
         \array_values( Md2PdfApp::getAllFilesByRegex( '~.+\.tpl$~i', true ) )
      );

      $this->_footerTpl = \trim( $this->_helper->ask( $input, $this->_output, $this->_questionFooterTpl ) );

      if ( '' !== $this->_footerTpl && \file_exists( $this->_footerTpl ) )
      {
         if ( ! \preg_match( '~^(/|[a-z]:)~i', $this->_footerTpl ) && 0 !== \stripos( $this->_footerTpl, '{CWD}' ) )
         {
            $this->_footerTpl = '{CWD}/' . $this->_footerTpl;
         }
      }

   }

   private function _getUserCssFiles( InputInterface $input )
   {

      if ( 0 < \count( $this->_userCssFiles ) )
      {
         return;
      }

      // Get all CSS files inside CWD recursive for auto completion
      // And use them as auto complete values
      $this->_questionUserCSS->setAutocompleterValues(
         \array_values( Md2PdfApp::getAllFilesByRegex( '~.+\.css$~i', true ) )
      );

      $cssFile  = null;
      while ( '' !== $cssFile )
      {

         $cssFile = \trim( $this->_helper->ask( $input, $this->_output, $this->_questionUserCSS ) );

         if ( '' === $cssFile ) { continue; }

         if ( ! \file_exists( $cssFile ) )
         {
            continue;
         }

         if ( ! \preg_match( '~^(/|[a-z]:)~i', $cssFile ) && 0 !== \stripos( $cssFile, '{CWD}' ) )
         {
            $cssFile = '{CWD}/' . $cssFile;
         }

         $this->_userCssFiles[] = $cssFile;

      }

   }

}

