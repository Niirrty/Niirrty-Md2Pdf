<?php
/**
 * @author     Ni Irrty <niirrty+code@gmail.com>
 * @copyright  ©2017, Ni Irrty
 * @package    Niirrty\Md2Pdf
 * @since      2017-10-15
 * @version    0.3.0
 */


declare( strict_types=1 );


namespace Niirrty\Md2Pdf\TOC;


/**
 * Helps with HTML-related operations.
 */
trait HtmlHelper
{


   /**
    * Convert topLevel and depth to h1..h6 tags array
    *
    * @param  int $topLevel
    * @param  int $depth
    * @return array|string[]  Array of header tags; ex: ['h1', 'h2', 'h3']
    */
   protected function determineHeaderTags( int $topLevel, int $depth ) : array
   {

      $hRange  = \range( $topLevel, $topLevel + ( $depth - 1 ) );
      $allowed = [ 1, 2, 3, 4, 5, 6 ];

      return \array_map(
         function( $value ) { return 'h' . $value; },
         \array_intersect( $hRange, $allowed )
      );

   }

   /**
    * Traverse Header Tags in DOM Document
    *
    * @param \DOMDocument $domDocument
    * @param int          $topLevel
    * @param int          $depth
    * @return \ArrayIterator|\DomElement[]
    */
   protected function traverseHeaderTags( \DOMDocument $domDocument, int $topLevel, int $depth )
   {

      $xpath      = new \DOMXPath( $domDocument );

      $xpathQuery = \sprintf(
         "//*[%s]",
         \implode(
            ' or ',
            \array_map(
               function( $value ) { return \sprintf( 'local-name() = "%s"', $value ); },
               $this->determineHeaderTags( $topLevel, $depth )
            )
         )
      );

      $nodes      = [];

      foreach ( $xpath->query( $xpathQuery ) as $node )
      {
         $nodes[] = $node;
      }

      return new \ArrayIterator( $nodes );

   }

   /**
    * Is this a full HTML document
    *
    * Guesses, based on presence of <body>...</body> tags
    *
    * @param string $markup
    * @return bool
    */
   protected function isFullHtmlDocument( $markup )
   {

      return ( \strpos( $markup, "<body>" !== false ) && \strpos( $markup, "</body>" ) !== false );

   }


}

