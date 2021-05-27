<?php
namespace GoodPill\Utilities;

/**
 * A simple class for storing some data into string based comments
 */
class GpComments
{

    use \GoodPill\Traits\UsesArrayModelStorage;

    /**
     * A place to store the raw comment string
     * @var string
     */
    protected $raw_comment;

    /**
     * The seperator used to marke goodpill comments
     * @var string
     */
    protected $seperator = "gppa:content";

    /**
     * Should we force properties to be defined?
     * @var boolean
     */
    public $check_property = false;

    /**
     * Create the comment object, parse the string if it is passed in
     * @param string $comment Should be a string that contains data seperaed by the $seperator.
     */
    public function __construct(string $comment = null)
    {
        if (!is_null($comment)) {
            $this->parseComment($comment);
        }
    }

    /**
     * Parse the comment into a data array
     * @param string $comment Should be a string that contains data seperaed by the $seperator.
     * @return boolean True if the comment was parse and had data
     */
    public function parseComment(string $comment) : bool
    {
        $matches   = [];
        $has_match = preg_match($this->getPattern(), $comment, $matches);

        if ($has_match) {
            $this->raw_comment = $comment;
            $this->fromJSON($matches[1]);
            return true;
        }

        return false;
    }

    /**
     * Combine the old data and the stored data into a string to insert
     * @return string A Comment ready to store
     */
    public function toString() : string
    {
        return $this->stripCommentString()
                . "<{$this->seperator}>"
                . $this->toJSON(true)
                . "</{$this->seperator}>";
    }

    /**
     * Get the pattern used to find the data
     * @return string The pattern
     */
    protected function getPattern() : string
    {
        return "/<{$this->seperator}>(.*)<\/{$this->seperator}>/s";
    }

    /**
     * Strip the comment pattern off a string
     * @return string The stripped toString
     */
    protected function stripCommentString() : string
    {
        return preg_replace($this->getPattern(), '', $this->raw_comment);
    }
}
