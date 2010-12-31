<?php

/**
 * @package GoogleBooks
 * @author Matthew Hayes <Matthew.Hayes@AllThingsCode.com>
 */
final class Exception_BookNotFound extends Exception
{
    /**
     * Constructor
     */
    public function __construct( $message = 'Book Not Found', $code = 0, Exception $previousException = null )
    {
        // make sure everything is assigned properly
        parent::__construct( $message, $code, $previousException );
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
}

