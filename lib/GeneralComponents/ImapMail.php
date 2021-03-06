<?php

// include parts of local Zend Framework
set_include_path(dirname(dirname(__FILE__)) . PATH_SEPARATOR . get_include_path());

require_once 'Zend/Mail/Storage/Imap.php';

class GeneralComponents_ImapMail extends Menta_Component_AbstractTest
{

    /**
     * @var Zend_Mail_Storage_Imap
     */
    protected $storage;

    /**
     * Wait for a mail whose subject contains a given string
     *
     * @param string $subject
     * @param int $timeout
     * @param int $sleep
     * @return int idx
     */
    public function waitForMailWhoseSubjectContains($subject, $timeout = 100, $sleep = 10)
    {
        $parent = $this;

        $result = $this->getHelperWait()->wait(function () use ($subject, $parent) {
            return $parent->searchMailWithSubject($subject);
            /* @var $parent GeneralComponents_ImapMail */
        }, $timeout, $sleep);

        if (!$result) {
            $this->getTest()->fail("Searching for mail with subject '$subject' timed out");
        }
        return $result;
    }

    /**
     * Delete all mails matching a given subject
     *
     * @param $subject
     * @return int
     */
    public function deleteAllMailsMatching($subject)
    {
        $ids = array();
        $storage = $this->getStorage(true); // get new storage (triggering fresh lookup for new mails)
        foreach ($storage as $idx => $message) {
            /* @var $message Zend_Mail_Message */
            if (strpos($message->subject, $subject) !== false) {
                $ids[] = $idx;
            }
        }
        foreach ($ids as $idx) {
            $storage->removeMessage($idx);
        }
        return $ids;
    }

    /**
     * Get parameters from configuration
     *
     * @return array
     */
    protected function getParamsFromConfiguration()
    {
        $params = array();
        if ($this->getConfiguration()->issetKey('testing.email.host')) {
            $params['host'] = $this->getConfiguration()->getValue('testing.email.host');
        }
        if ($this->getConfiguration()->issetKey('testing.email.port')) {
            $params['port'] = $this->getConfiguration()->getValue('testing.email.port');
        }
        if ($this->getConfiguration()->issetKey('testing.email.user')) {
            $params['user'] = $this->getConfiguration()->getValue('testing.email.user');
        }
        if ($this->getConfiguration()->issetKey('testing.email.password')) {
            $params['password'] = $this->getConfiguration()->getValue('testing.email.password');
        }
        if ($this->getConfiguration()->issetKey('testing.email.ssl')) {
            $params['ssl'] = (bool)$this->getConfiguration()->getValue('testing.email.ssl');
        }
        $this->getTest()->assertNotEmpty($params, 'No mailbox parameters found in testing.email.');
        return $params;
    }

    /**
     * Get new storage object
     *
     * @param bool $forceNew
     * @return Zend_Mail_Storage_Imap
     */
    public function getStorage($forceNew = false)
    {
        if (is_null($this->storage) || $forceNew) {
            $this->storage = new Zend_Mail_Storage_Imap($this->getParamsFromConfiguration());
        }
        return $this->storage;
    }

    /**
     * Search for a mail whose subject contains the given string
     *
     * @param $subject
     * @return bool|int
     */
    public function searchMailWithSubject($subject)
    {
        $storage = $this->getStorage(true); // get new storage (triggering fresh lookup for new mails)
        foreach ($storage as $idx => $message) {
            /* @var $message Zend_Mail_Message */
            if (strpos(iconv_mime_decode($message->subject, 0, 'UTF-8'), $subject) !== false) {
                return $idx;
            }
        }
        return false;
    }

    /**
     * get content for mail whose content contains the given string and delete the mail then
     *
     * @param string $subjectContains
     * @param bool $useXPath
     * @param int $timeout
     * @param int $sleep
     * @return mixed string|DOMXPath
     */
    public function getMailContent($subjectContains, $useXPath = false, $timeout = 100, $sleep = 10)
    {
        $idx = $this->waitForMailWhoseSubjectContains($subjectContains, $timeout, $sleep);
        $message = $this->getStorage()->getMessage($idx);

        $content = Zend_Mime_Decode::decodeQuotedPrintable($message->getContent());
        $this->getTest()->assertNotEmpty($content);
        $this->getStorage()->removeMessage($idx);

        if ($useXPath) {
            preg_match('/<body.*<\/body>/misU', $content, $match);
            $html = str_replace(array('&lt;', '&gt;'), array('<', '>'), htmlentities($match[0], ENT_NOQUOTES));

            return new DOMXPath(DOMDocument::loadHTML($html));
        }

        return $content;
    }

    /**
     * get Mail as XPATH object to get elements via xpath
     * please make sure to have valid html to make this work
     *
     * @param $subjectContains
     * @param int $timeout
     * @param int $sleep
     * @return DOMXPath
     */
    public function getHTMLMailContent($subjectContains, $timeout = 100, $sleep = 10)
    {
        return $this->getMailContent($subjectContains, true, $timeout, $sleep);
    }

    /**
     * returns just the plain mail content
     *
     * @param $subjectContains
     * @param int $timeout
     * @param int $sleep
     * @return string
     */
    public function getTextMailContent($subjectContains, $timeout = 100, $sleep = 10)
    {
        return $this->getMailContent($subjectContains, false, $timeout = 100, $sleep = 10);
    }

}
