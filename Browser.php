<?php
require_once 'Zend/Gdata/Books.php';
require_once 'Zend/Gdata/ClientLogin.php';
require_once 'Exception/BookNotFound.php';

/**
 * Rerence:  http://garykac.blogspot.com/2010/06/using-google-books-api.html
 *           http://framework.zend.com/manual/en/zend.gdata.books.html
 *
 * @package GoogleBooks
 * @author Matthew Hayes <Matthew.Hayes@AllThingsCode.com>
 */
final class GoogleBooks_Browser
{
    /**
     * All properties for this object are stored in this array.
     * Default values are not populated,
     *   so if properties are accessed before they are set,
     *   a php notice is generated.  This behavior helps identify coding errors.
     * @var array
     */
    private $_properties = array();
    // ------------------------------------------------------------------------


    /**
     * constructor
     */
    public function __construct()
    {
        // Default the IsSignedInFlag value
        $this->_setIsSignedInFlag( false );
    }


    /**
     * This helps prevent invalid property assignments.
     * @param string
     * @param mixed
     */
    public function __set( $propertyName, $propertyValue )
    {
        throw new Exception( 'Invalid property assignment: ' . $propertyName . ' => ' . $propertyValue );
    }
    /**
     * This helps catch invalid property retreival
     * @param string
     */
    public function __get( $propertyName )
    {
        throw new Exception( 'Invalid property retreival: ' . $propertyName );
    }



    // ----- Setters/Getters --------------------------------------------------

    /**
     * @param string
     */
    public function setUsername( $newValue )
    {
        $this->_properties['Username'] = $newValue;
    }
    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->_properties['Username'];
    }


    /**
     * @param string
     */
    public function setPassword( $newValue )
    {
        $this->_properties['Password'] = $newValue;
    }
    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->_properties['Password'];
    }


    /**
     * @param bool
     */
    private function _setIsSignedInFlag( $newValue )
    {
        $this->_properties['IsSignedInFlag'] = $newValue;
    }
    /**
     * @return bool
     */
    public function getIsSignedInFlag()
    {
        return $this->_properties['IsSignedInFlag'];
    }
    /**
     * @return bool
     */
    public function isSignedIn()
    {
        return $this->getIsSignedInFlag();
    }


    /**
     * @param Zend_Gdata_Books
     */
    private function _setZendGdataBooks( Zend_Gdata_Books $newValue )
    {
        $this->_properties['ZendGdataBooks'] = $newValue;
    }
    /**
     * @return Zend_Gdata_Books
     */
    private function _getZendGdataBooks()
    {
        return $this->_properties['ZendGdataBooks'];
    }
    // ------------------------------------------------------------------------




    // ----- Public Methods ---------------------------------------------------

    /**
     * Most methods in this class
     *   will automatically call this method if we are not already signed in.
     * @throws Exception
     */
    public function signIn()
    {
        // Only sign in once
        if( true === $this->isSignedIn() ) {
            return;
        }

        $googleClient = Zend_Gdata_ClientLogin::getHttpClient( $this->getUsername(), $this->getPassword(), Zend_Gdata_Books::AUTH_SERVICE_NAME );
        $googleBooks  = new Zend_Gdata_Books( $googleClient );
        $this->_setZendGdataBooks( $googleBooks );

        // Ladies and gentlemen, we are now signed into Audible.com.
        $this->_setIsSignedInFlag( true );
    }


    /**
     * @param string
     * @param string
     * @return array
     */
    public function findGoogleBook( $title, $author )
    {
        $gdataQueryString = $this->_createGoogleBookSearchQuery( $title, $author );
        //$gdataQueryString = 'Atlas Shrugged';
        //echo "\ngdataQueryString:  {$gdataQueryString}\n";

        $gBooks = new Zend_Gdata_Books();
        $gVolumeQuery = $gBooks->newVolumeQuery();
        $gVolumeQuery->setQuery( $gdataQueryString );
        $gFeed = $gBooks->getVolumeFeed( $gVolumeQuery );
        //var_dump( $gFeed );

        // If don't find any matches, then we're all done here
        if ( $gFeed->count() < 1 ) {
            throw new Exception_BookNotFound(
                'No matching google books found for ' .
                'title: '  . $title   . ', ' .
                'author: ' . $author
                );
        }

        // Assume the first match is the most correct
        $gFeed->rewind();
        $firstFeedEntry = $gFeed->current();
        //var_dump( $firstFeedEntry );

        /*
        echo "\nFound these books:\n";
        foreach ( $gFeed as $gEntry  ) {
            echo implode( '|', $gEntry->getTitles() ) . '; ' . $gEntry->getVolumeId() . "\n" ;
        }
        return '';
        */

        return $firstFeedEntry->getVolumeId();
    }


    /**
     * @param string
     * @param string
     * @return bool
     */
    public function isBookOnBookshelf( $bookshelfVolumeId, $bookVolumeId )
    {
        // Make sure we're signed in
        if ( false === $this->isSignedIn() ) {
            $this->signIn();
        }

        $bookshelfUri = 'http://books.google.com/books/feeds/users/me/collections/' . $bookshelfVolumeId . '/volumes';

        $gBooks = $this->_getZendGdataBooks();
        $gFeed = $gBooks->getVolumeFeed( $bookshelfUri );       

        foreach ( $gFeed as $gEntry  ) {
            if ( $gEntry->getVolumeId() === $bookVolumeId ) {
                // We found a match, so return TRUE
                return true;
            }
        }

        // No match was found
        return false;
    }


    /**
     * NOTE: To get a list of bookshelfes:  http://books.google.com/books/feeds/users/me/collections
     * @param string
     * @params string
     */
    public function addBookToBookShelf( $bookshelfVolumeId, $bookVolumeId )
    {
        // Make sure we're signed in
        if ( false === $this->isSignedIn() ) {
            $this->signIn();
        }
        
        $bookshelfUri = 'http://books.google.com/books/feeds/users/me/collections/' . $bookshelfVolumeId . '/volumes';

        $gNewBookshelfEntry = new Zend_Gdata_Books_VolumeEntry();
        $gNewBookshelfEntry->setId( new Zend_Gdata_App_Extension_Id( $bookVolumeId ) );

        $gBooks = $this->_getZendGdataBooks();
        $gBooks->insertVolume( $gNewBookshelfEntry, $bookshelfUri );
    }
    // ------------------------------------------------------------------------




    // ----- Private Methods --------------------------------------------------

    /**
     * @param string
     * @param string
     * @return string
     */
    private function _createGoogleBookSearchQuery( $title, $author = '' )
    {
        $title = preg_replace( '/\s+/', ' ', trim( $title ) );
        $title = preg_replace( '/[^A-Z0-9 ]/i', '', $title );
        $titleQuery  = 'intitle:' . str_replace( ' ', ' intitle:', $title );

        $author = preg_replace( '/\s+/', ' ', trim( $author ) );
        $author = preg_replace( '/[^A-Z0-9 ]/i', '', $author );
        $authorQuery = 'inauthor:' . str_replace( ' ', ' inauthor:', $author );

        $totalQuery = trim( $titleQuery . ' ' . $authorQuery );
        $totalQuery = str_replace( ' ', '+', $totalQuery );

        return $totalQuery;
    }



    // ------------------------------------------------------------------------
}
