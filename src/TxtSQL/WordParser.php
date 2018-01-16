<?php

namespace TxtSQL;

/**
 * Tokenizes a string into components for analysis by a lexer and/or parser
 *
 * @package wordParser
 * @author  Faraz Ali <FarazAli at Gmail dot com>
 * @version 3.0 BETA
 */
class WordParser
{
    /**
     * The current character index
     *
     * @var bool
     */
    public $characterIndex = -1;

    /**
     * The last word returned successfully
     */
    private $lastword = '';

    /**
     * The string that gets tokenized
     *
     * @var string
     */
    public $word = '';

    /**
     * Class constructor, sets the statement that will be broken up
     *
     * @param string $string The string that should be tokenized
     *
     * @return bool $success Whether the string was accepted as valid
     */
    public function __construct($string)
    {
        if (!$this->setString($string)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the last successfully parsed word
     *
     * @return string $lastword The last word parsed successfully
     */
    public function getLastWord()
    {
        return $this->lastword;
    }

    /**
     * Sets the string and resets the current character index
     *
     * @param string $string              The string that should be tokenized
     * @param bool   $resetCharacterIndex Whether to reset the current
     *                                    character index
     *
     * @return bool
     */
    public function setString($string, $resetCharacterIndex = true)
    {
        if (is_string($string)) {
            $this->word = $string;

            if ($resetCharacterIndex === true) {
                $this->characterIndex = -1;
            }

            return true;
        }

        return false;
    }

    /**
     * Fetches the next word that is in the string
     *
     * @param bool   $leaveQuotes      Whether to leave quotes part of the
     *                                 string or to remove them
     * @param string $whitespace_chars Characters that are considered
     *                                 whitespace
     * @param bool   $checkQuotes      Checks whether the current word is
     *                                 inside a string, and if it is, then
     *                                 concatenate it with the next word
     *
     * @return string $word The next word in the string
     */
    public function getNextWord(
        $leaveQuotes = false,
        $whitespace_chars = " \t\r\n",
        $checkQuotes = false
    ) {
        /* Create some variables */
        $word = '';
        $escaped = false;
        $inComment = false;
        $inSQuotes = false;
        $inDQuotes = false;
        $inBrackets = 0;

        /* Go through each letter in the string until there are none left or
           there is a new word */
        while (($c = $this->getNextLetter()) !== false) {
            /* Inside a comment */
            if ($inComment === true) {
                if ($c == '*' && $this->word{$this->characterIndex + 1} == '/') {
                    $inComment = false;
                    $this->characterIndex++;
                }

                continue;
            } /* Start of a comment */
            elseif ($c == '/' && $this->word{$this->characterIndex + 1} == '*') {
                if ($inSQuotes === true || $inDQuotes === true) {
                    $word .= '/';
                    continue;
                }

                $inComment = true;
                continue;
            }

            /* This character is escaped */
            if ($escaped === true) {
                $escaped = false;
                $word .= $c;
                continue;
            } /* The next character should be interpreted as is */
            elseif ($c == '\\') {
                $escaped = true;
                continue;
            } /* Start of a single quote word */
            elseif ($c == "'") {
                /* If we are not in double quotes */
                if ($inDQuotes !== true) {
                    /* If we are already in single quotes, then
this is the end of the word */
                    if ($inSQuotes === true) {
                        $inSQuotes = false;

                        /* Check whether to leave the quotes */
                        if ($leaveQuotes === true || $inBrackets != 0) {
                            $word .= $c;

                            if ($inBrackets != 0) {
                                continue;
                            }
                        }

                        /* If the brackets index is down to 0, then this
                        is the end of the word */
                        if ($inBrackets == 0) {
                            if ($checkQuotes === true) {
                                continue;
                            }

                            $this->characterIndex++;

                            return $word;
                        }
                    } /* Start of a single quote word */
                    else {
                        $inSQuotes = true;
                    }

                    /* Check whether to leave quotes */
                    if ($leaveQuotes === false && $inBrackets == 0) {
                        continue;
                    }
                }
            } /* Start of a double quote string */
            elseif ($c == '"') {
                /* If we are not in single quotes */
                if ($inSQuotes !== true) {
                    /* If we are already in double quotes */
                    if ($inDQuotes === true) {
                        /* This is the end of the double-quote word */
                        $inDQuotes = false;

                        /* Check whether to leave the quotes */
                        if ($leaveQuotes === true || $inBrackets != 0) {
                            $word .= $c;

                            if ($inBrackets != 0) {
                                continue;
                            }
                        }

                        if ($inBrackets == 0) {
                            if ($checkQuotes === true) {
                                continue;
                            }

                            $this->characterIndex++;

                            return $word;
                        }
                    } /* Start of a double-quote word */
                    else {
                        $inDQuotes = true;
                    }

                    /* Check whether to leave quotes */
                    if ($leaveQuotes === false && $inBrackets == 0) {
                        continue;
                    }
                }
            } /* Start of a bracket */
            elseif ($c == '(') {
                if ($inSQuotes !== true) {
                    if ($inDQuotes !== true) {
                        $inBrackets++;
                    }
                }
            } /* End of a bracket */
            elseif ($c == ')') {
                if ($inSQuotes !== true) {
                    if ($inDQuotes !== true) {
                        $inBrackets--;
                    }
                }
            } /* This character is in a quotation ( single or double ) */
            elseif ($inSQuotes === true || $inDQuotes === true
                || $inBrackets != 0) {
                $word .= $c;
                continue;
            } /* End of an SQL statement */
            elseif ($c == ';') {
                $this->characterIndex--;

                return $word;
            } /* Eliminate whitespace characters */
            else {
                if (strpos($whitespace_chars, $c) !== false) {
                    if (trim($word, $whitespace_chars) == "") {
                        continue;
                    } else {
                        break;
                    }
                }
            }

            /* Append the current character to the word */
            $word .= $c;
        }

        /* Add a NULL byte to the end of the word */
        if ($this->characterIndex < strlen($this->word)) {
            if ($this->word{$this->characterIndex - 1} != null) {
                $word .= null;
            }
        }

        /* Return the word */

        return $this->lastword = ($word == '0' ? '00' : $word);
    }

    /**
     * Fetches the next character in the string
     *
     * @return string $c The next character
     */
    public function getNextLetter()
    {
        /* Increment the character index */
        $this->characterIndex++;

        /* If there is another letter, then return it */
        if ($this->characterIndex < strlen($this->word)) {
            return $this->word{$this->characterIndex};
        }

        return false;
    }

    /**
     * Issues a syntax error and gives part of string where error occurrs
     *
     * @param mixed $arguments The arguments are set to boolean false to
     *                         indicate that error has occurred
     *
     * @return bool
     */
    protected function throwSyntaxError(&$arguments)
    {
        $arguments = false;
        $error = substr($this->word, $this->characterIndex - 10, $this->characterIndex + 10);
        TxtSQL::_error(E_USER_NOTICE, "Syntax error near `$error`");

        return true;
    }
}