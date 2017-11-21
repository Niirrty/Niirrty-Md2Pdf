<?php
/**
 * @author     Ni Irrty <niirrty+code@gmail.com>
 * @copyright  ©2017, Ni Irrty
 * @package    Niirrty\Md2Pdf
 * @since      2017-10-11
 * @version    0.1.0
 */


declare( strict_types=1 );


namespace Niirrty\Md2Pdf;


use \Symfony\Component\Console\Application;
use \Niirrty\Md2Pdf\Commands\Build;
use \Niirrty\Md2Pdf\Commands\Init;


class Md2PdfApp extends Application
{


   // <editor-fold desc="// – – –   C O N S T A N T S   – – – – – – – – – – – – – – – – – – – – – – – – – – – – –">

   public const NAME       = 'Niirrty Markdown to PDF converter';
   public const VERSION    = '0.1.1';
   public const BUILD_DATE = '2017-11-21';

   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   C O N S T R U C T O R   – – – – – – – – – – – – – – – – – – – –">

   /**
    * Md2PdfApp constructor.
    */
   public function __construct()
   {

      parent::__construct( static::NAME, static::VERSION . ' (' . static::BUILD_DATE . ')' );
      $this->add( new Init() );
      $this->add( new Build() );

   }

   // </editor-fold>


   // <editor-fold desc="// – – –   P U B L I C   S T A T I C   M E T H O D S   – – – – – – – – – – – – – – – – –">

   /**
    * Gets all MD files inside the current working dir.
    *
    * @param  bool $recursive Scan subdirectories also?
    * @return array
    */
   public static function getAllMdFiles( bool $recursive = false ) : array
   {

      return static::getAllFilesByRegex( '~.+\.md$~i', $recursive );

   }

   /**
    * Gets all files where file name matches a specific regex, inside the current working dir.
    *
    * @param  string $regex
    * @param  bool $recursive Scan subdirectories also?
    * @return array
    */
   public static function getAllFilesByRegex( string $regex = '~.+\.md$~i', bool $recursive = false ) : array
   {

      $baseDir = \getcwd();

      if ( $recursive )
      {
         $dirIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $baseDir, \RecursiveDirectoryIterator::SKIP_DOTS )
         );
      }
      else
      {
         $dirIterator = new \RecursiveDirectoryIterator( $baseDir, \RecursiveDirectoryIterator::SKIP_DOTS );
      }

      $filesIterator = new \RegexIterator( $dirIterator, $regex, \RecursiveRegexIterator::GET_MATCH );

      $filesOut = [];

      $baseDirReplaceRegex = '~^' . \preg_quote( \rtrim( $baseDir, '\\/' ), '~' ) . '[/\\\\]~i';

      foreach ( $filesIterator as $tmp )
      {
         if ( \is_string( $tmp ) )
         {
            $filesOut[ $tmp ] = \preg_replace( $baseDirReplaceRegex, '', $tmp );
            continue;
         }
         if ( ! \is_array( $tmp ) || 1 > \count( $tmp ) )
         {
            continue;
         }
         foreach ( $tmp as $tmp1 )
         {
            $filesOut[ $tmp1 ] = \preg_replace( $baseDirReplaceRegex, '', $tmp1 );
         }
      }

      \ksort( $filesOut );

      return $filesOut;

   }

   // </editor-fold>


}

