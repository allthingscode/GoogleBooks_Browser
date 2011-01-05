<?php
require_once 'Zend/Gdata/Books.php';
require_once 'Zend/Gdata/ClientLogin.php';
require_once 'Exception/BookNotFound.php';

/**
 * Reference:  http://garykac.blogspot.com/2010/06/using-google-books-api.html
 *             http://framework.zend.com/manual/en/zend.gdata.books.html
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

        // Default to at least 2 seconds between google api requests.
        $this->_setMinDelayBetweenRequests( 1 );
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
    /**
     * @return bool
     */
    private function _hasZendGdataBooks()
    {
        $hasZendGdataBooks = ( true === is_null( $this->_properties['ZendGdataBooks'] ) );
        return $hasZendGdataBooks;
    }


    /**
     * @param int
     */
    private function _setMinDelayBetweenRequests( $newValue )
    {
        $this->_properties['MinDelayBetweenRequests'] = $newValue;
    }
    /**
     * @return int
     */
    private function _getMinDelayBetweenRequests()
    {
        return $this->_properties['MinDelayBetweenRequests'];
    }


    /**
     * @param int
     */
    private function _setLastRequestTime( $newValue )
    {
        $this->_properties['LastRequestTime'] = $newValue;
    }
    /**
     * @return int
     */
    private function _getLastRequestTime()
    {
        return $this->_properties['LastRequestTime'];
    }
    /**
     * @return bool
     */
    private function _hasLastRequestTime()
    {
        $hasLastRequestTime = ( true === array_key_exists( 'LastRequestTime', $this->_properties ) );
        return $hasLastRequestTime;
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

        $this->_setIsSignedInFlag( true );
    }


    /**
     * @param string
     * @param Zend_Gdata_Books_VolumeEntry
     * @return bool
     */
    public function isBookOnBookshelf( $bookshelfVolumeId, Zend_Gdata_Books_VolumeEntry $bookVolumeEntry )
    {
        // Make sure we're signed in
        if ( false === $this->isSignedIn() ) {
            $this->signIn();
        }

        static $booksFoundOnBookshelf = array();

        $gdataQueryString = $this->_createExactGoogleBookSearchQuery( $bookVolumeEntry );

        // See if we already know that it's on our bookshelf
        if ( true === in_array( $gdataQueryString, $booksFoundOnBookshelf ) ) {
            return true;
        }

        try {
            $firstFeedEntry = $this->_getFirstMatch( $gdataQueryString, $bookshelfVolumeId );
        } catch ( Exception_BookNotFound $exception ) {
            //echo "\n" . __FUNCTION__ . "()\t" . $exception->getMessage();
            return false;
        }

        // Save the fact that the book is already on our bookshelf
        $booksFoundOnBookshelf[] = $gdataQueryString;

        return true;
    }


    /**
     * @param string
     * @param string
     * @return Zend_Gdata_Books_VolumeEntry
     */
    public function findGoogleBook( $title, $author )
    {
        // Make sure we're signed in
        if ( false === $this->isSignedIn() ) {
            $this->signIn();
        }

        $gdataQueryString = $this->_createGoogleBookSearchQuery( $title, $author );

        $firstFeedEntry = $this->_getFirstMatch( $gdataQueryString );

        return $firstFeedEntry;
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

        static $addedBooks = array();

        // Prevent adding the same book more than once
        $paramKey = $bookshelfVolumeId . '|' . $bookVolumeId;
        if ( true === array_key_exists( $paramKey, $addedBooks ) ) {
            return;
        }

        $bookshelfUri = 'http://books.google.com/books/feeds/users/me/collections/' . $bookshelfVolumeId . '/volumes';

        $gNewBookshelfEntry = new Zend_Gdata_Books_VolumeEntry();
        $gNewBookshelfEntry->setId( new Zend_Gdata_App_Extension_Id( $bookVolumeId ) );

        $gBooks = $this->_getZendGdataBooks();
        $gBooks->insertVolume( $gNewBookshelfEntry, $bookshelfUri );

        $addedBooks[ $paramKey ] = true;
    }
    // ------------------------------------------------------------------------




    // ----- Private Methods --------------------------------------------------



    /**
     * @param string
     * @param string
     * @return Zend_Gdata_Books_VolumeEntry
     */
    private function _getFirstMatch( $gdataQueryString, $bookshelfVolumeId = null )
    {
        $this->_throttle();

        $gBooks = $this->_getZendGdataBooks();

        if ( true === is_null( $bookshelfVolumeId ) ) {
            $gVolumeQuery = $gBooks->newVolumeQuery();
        } else {
            $bookshelfUri = 'http://books.google.com/books/feeds/users/me/collections/' . $bookshelfVolumeId . '/volumes';
            $gVolumeQuery = $gBooks->newVolumeQuery( $bookshelfUri );
        }
        $gVolumeQuery->setQuery( $gdataQueryString );
        $gVolumeQuery->setMaxResults( 1 );
        $gFeed = $gBooks->getVolumeFeed( $gVolumeQuery );
        $this->_setLastRequestTime( time() );

        // If don't find any matches, then we're all done here
        if ( $gFeed->count() < 1 ) {
            $errorMessage = 'No matching google books found';
            $errorMessage .= "\n" . 'Using: ' . $gdataQueryString;
            throw new Exception_BookNotFound( $errorMessage );
        }

        // Assume the first match is the most correct
        $gFeed->rewind();
        $firstFeedEntry = $gFeed->current();

        return $firstFeedEntry;
    }


    /**
     * @param string
     * @param string
     * @return string
     */
    private function _createGoogleBookSearchQuery( $title, $author )
    {
        $title = preg_replace( '/\s+/', ' ', trim( $title ) );
        $title = preg_replace( '/[(][^)]+[)]/', '', $title );
        $title = preg_replace( '/\s+/', ' ', trim( $title ) );
        $title = preg_replace( '/[^-A-Z0-9=\'$@ ]/i', '', $title );
        $title = preg_replace( '/\s+/', ' ', trim( $title ) );
        $title = preg_replace( '/(Part|Volume) [0-9]+/i', '', $title );
        $title = preg_replace( '/\s+/', ' ', trim( $title ) );
        $titleQuery  = 'intitle:' . str_replace( ' ', ' intitle:', $title );

        $author = preg_replace( '/\s+/', ' ', trim( $author ) );
        $author = preg_replace( '/[^-A-Z0-9\' ]/i', '', $author );
        $authorQuery = 'inauthor:' . str_replace( ' ', ' inauthor:', $author );

        $totalQuery = trim( $titleQuery . ' ' . $authorQuery );
        $totalQuery = str_replace( ' ', '+', $totalQuery );

        return $totalQuery;
    }


    /**
     * @param Zend_Gdata_Books_VolumeEntry
     * @return string
     */
    private function _createExactGoogleBookSearchQuery( Zend_Gdata_Books_VolumeEntry $bookVolumeEntry )
    {
        $titleQuery = '';
        foreach ( $bookVolumeEntry->getTitles() as $bookVolumeTitle ) {
            $titleQuery .= ' intitle:"' . $bookVolumeTitle->getText() . '"';
        }
        $titleQuery = trim( $titleQuery );

        $authorQuery = '';
        foreach ( $bookVolumeEntry->getCreators() as $bookVolumeCreator ) {
            $authorQuery .= ' inauthor:"' . $bookVolumeCreator->getText() . '"';
        }
        $authorQuery = trim( $authorQuery );

        $totalQuery = trim( $titleQuery . ' ' . $authorQuery );
        $totalQuery = str_replace( ' ', '+', $totalQuery );

        return $totalQuery;
    }



    /**
     *
     */
    private function _throttle()
    {
        // If we haven't submitted any requests yet, then no need to delay.
        if ( false === $this->_hasLastRequestTime() ) {
            return;
        }

        // Stall as long as required
        $delayTime = ( time() - $this->_getLastRequestTime() );
        while ( $delayTime < $this->_getMinDelayBetweenRequests() ) {
            sleep( 1 );
            $delayTime = ( time() - $this->_getLastRequestTime() );
        }
    }
    // ------------------------------------------------------------------------
}
