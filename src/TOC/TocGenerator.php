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


use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Knp\Menu\Renderer\RendererInterface;
use Masterminds\HTML5;


/**
 * Table Of Contents Generator generates TOCs from HTML Markup
 *
 * @author Casey McLaughlin <caseyamcl@gmail.com>
 */
class TocGenerator
{


   use HtmlHelper;


   /**
    * @var HTML5
    */
   private $domParser;

   /**
    * @var MenuFactory
    */
   private $menuFactory;


   /**
    * Constructor
    *
    * @param MenuFactory $menuFactory
    * @param HTML5       $htmlParser
    */
   public function __construct( MenuFactory $menuFactory = null, HTML5 $htmlParser = null )
   {

      $this->domParser   = $htmlParser  ?: new HTML5();
      $this->menuFactory = $menuFactory ?: new MenuFactory();

   }


   /**
    * Get Menu
    *
    * Returns a KNP Menu object, which can be traversed or rendered
    *
    * @param string  $markup    Content to get items fro $this->getItems($markup, $topLevel, $depth)m
    * @param int     $topLevel  Top Header (1 through 6)
    * @param int     $depth     Depth (1 through 6)
    * @return ItemInterface     KNP Menu
    */
   public function getMenu( string $markup, int $topLevel = 1, int $depth = 6 ) : ItemInterface
   {

      // Setup an empty menu object
      $menu = $this->menuFactory->createItem( 'TOC' );

      // Parse HTML
      $tagsToMatch = $this->determineHeaderTags( $topLevel, $depth );
      // Initial settings
      $lastElem    = $menu;

       \preg_match_all( '~<(h[1-6])><a name="([^"]+)">([^<\\r\\n]+)</a>~', $markup, $headerMatches );

      for ( $i = 0, $c = \count( $headerMatches[ 0 ] ); $i < $c; $i++ )
      {

         // Get the TagName and the level
         $tagName = $headerMatches[ 1 ][ $i ];
         $level   = \array_search( \strtolower( $tagName ), $tagsToMatch ) + 1;

         // Determine parent item which to add child
         if ( 1 === $level )
         {
            $parent = $menu;
         }
         else if ( $level === $lastElem->getLevel() )
         {
            $parent = $lastElem->getParent();
         }
         else if ( $level > $lastElem->getLevel() )
         {
            $parent = $lastElem;
            for ( $j = $lastElem->getLevel(); $j < ($level - 1); $j++ )
            {
               $parent = $parent->addChild( '' );
            }
         }
         else
         {
            //if ($level < $lastElem->getLevel())
            $parent = $lastElem->getParent();
            while ( $parent->getLevel() > $level - 1 )
            {
               $parent = $parent->getParent();
            }
         }

         $lastElem = $parent->addChild(
            $headerMatches[ 3 ][ $i ],
            [ 'uri' => '#' . $headerMatches[ 2 ][ $i ] ]
         );

      }

      return $menu;

   }


   /**
    * Get HTML Links in list form
    *
    * @param string            $markup   Content to get items from
    * @param int               $topLevel Top Header (1 through 6)
    * @param int               $depth    Depth (1 through 6)
    * @param RendererInterface $renderer
    * @return string HTML <LI> items
    */
   public function getHtmlMenu( string $markup, int $topLevel = 1, int $depth = 6, RendererInterface $renderer = null )
   {

      if ( ! ( $renderer instanceof ListRenderer ) )
      {
         $renderer = new ListRenderer(
            new Matcher(),
            [
               'currentClass'  => 'active',
               'ancestorClass' => 'active_ancestor'
            ]
         );
      }

      return $renderer->render( $this->getMenu( $markup, $topLevel, $depth ) );

   }


}

