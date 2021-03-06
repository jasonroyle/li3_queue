<?php

namespace li3_queue\net\beanstalk;

class Response extends \lithium\core\Object {

	public $id = null;

	public $status = null;

	public $bytes = null;

	public $data = null;

	protected $_responseTypes = array(
		'/(?<status>OUT_OF_MEMORY)/',
		'/(?<status>INTERNAL_ERROR)/',
		'/(?<status>BAD_FORMAT)/',
		'/(?<status>UNKNOWN_COMMAND)/',
		'/(?<status>INSERTED)\s(?<id>\d+)/',
		'/(?<status>BURIED)\s(?<id>\d+)/',
		'/(?<status>EXPECTED_CRLF)/',
		'/(?<status>JOB_TOO_BIG)/',
		'/(?<status>DRAINING)/',
		'/(?<status>USING)\s(?<tube>.+)/',
		'/(?<status>DEADLINE_SOON)/',
		'/(?<status>TIMED_OUT)/',
		'/(?<status>RESERVED)\s(?<id>\d+)\s(?<bytes>\d+)/',
		'/(?<status>DELETED)/',
		'/(?<status>NOT_FOUND)/',
		'/(?<status>RELEASED)/',
		'/(?<status>TOUCHED)/',
		'/(?<status>WATCHING)\s(?<count>\d+)/',
		'/(?<status>NOT_IGNORED)/',
		'/(?<status>FOUND)\s(?<id>\d+)\s(?<bytes>\d+)/s',
		'/(?<status>KICKED)\s(?<count>\d+)/',
		'/(?<status>KICKED)/',
		'/(?<status>OK)\s(?<bytes>\d+)/s',
		'/(?<status>PAUSED)/'
	);

	public function __construct(array $config = array()) {
		parent::__construct($config);

		if($this->_config['message']) {
			$this->_parseResponse($this->_config['message']);
		}
	}

	protected function _parseResponse($message) {
		foreach ($this->_responseTypes as $pattern) {
			if(preg_match($pattern, $message, $match)) {
				$this->id = (isset($match['id'])) ? $match['id'] : null ;
				$this->status = (isset($match['status'])) ? (string) $match['status'] : null ;
				$this->tube = (isset($match['tube'])) ? (string) $match['tube'] : null ;
				$this->bytes = (isset($match['bytes'])) ? (integer) $match['bytes'] : null ;
				$this->count = (isset($match['count'])) ? (integer) $match['count'] : null ;
			}
		}
		return null;
	}

}

?>