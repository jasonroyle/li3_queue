<?php

namespace li3_queue\extensions\adapter\queue;

use AMQPConnection;
use AMQPChannel;
use AMQPExchange;
use AMQPQueue;
use AMQPEnvelope;

class AMQP extends \li3_queue\extensions\adapter\Queue {

	/**
	 * `AMQPConnection` object instance used by this adapter.
	 *
	 * @var object
	 */
	public $connection = null;

	/**
	 * `AMQPChannel` object instance used by this adapter.
	 *
	 * @var object
	 */
	public $channel = null;

	/**
	 * `AMQPExchange` object instance used by this adapter.
	 *
	 * @var object
	 */
	public $exchange = null;

	/**
	 * `AMQPQueue` object instance used by this adapter.
	 *
	 * @var object
	 */
	public $queue = null;

	/**
	 * `AMQPEnvelope` object instance used by this adapter.
	 *
	 * @var object
	 */
	public $envelope = null;

	/**
	 * Adds config values to the public properties when a new object is created.
	 *
	 * @param array $config Configuration options : default value
	 *        - `'host'` _string_: '127.0.0.1'
	 *        - `'login'` _string_: 'guest'
	 *        - `'password'` _string_: 'guest'
	 *        - `'port'` _interger_: 5672
	 *        - `'vhost'` _string_: '/'
	 *        - `'exchange'` _string_: 'li3.default'
	 *        - `'queue'` _string_: 'li3.default'
	 *        - `'routingKey'` _mixed_: null
	 *        - `'autoAck'` _mixed_: 0
	 *        - `'minMessages'` _interger_: 0
	 *        - `'maxMessages'` _interger_: 1
	 *        - `'prefetchCount'` _interger_: 3
	 *        - `'autoConnect'` _interger_: 1
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'host' => '127.0.0.1',
			'login' => 'guest',
			'password' => 'guest',
			'port' => 5672,
			'vhost' => '/',
			'exchange' => 'li3.default',
			'queue' => 'li3.default',
			'routingKey' => null,
			'autoAck' => false,
			'minMessages' => 0,
			'maxMessages' => 1,
			'prefetchCount' => 3,
			'autoConnect' => 1
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Connect to the queue.
	 *
	 * @return .
	 */
	public function connect() {
		if(!$this->connection) {
			$this->connection = new AMQPConnection($this->_config);
			$this->connection->connect();
		}
		return $this->isConnected();
	}

	/**
	 * Check if the the queue is connected.
	 *
	 * @return .
	 */
	public function isConnected() {
		return $this->connection->isConnected();
	}

	/**
	 * Disconnect from an AMQP server
	 *
	 * @return .
	 */
	public function disconnect() {
		return $this->connection->disconnect();
	}

	/**
	 * Write value(s) to the queue.
	 *
	 * @return .
	 */
	public function write($data, array $options = array()) {
		$config = &$this->_config;

		$defaults = array('class' => 'message');
		$options += $defaults;

		$class = $options['class'];
		$params = array('data' => $data) + $options;

		$message = $this->invokeMethod('_instance', array($class, $params));
		return $this->publish($message);
	}

	/**
	 * Read value(s) from the queue.
	 *
	 * @return .
	 */
	public function read(array $options = array()) {
		$this->nack();
		$message = $this->envelope($options);
		return $message;
	}

	/**
	 * Alias for ack().
	 *
	 * @return .
	 */
	public function confirm($message) {
		return $this->ack();
	}

	/**
	 * Alias for nack().
	 *
	 * @return .
	 */
	public function requeue($message) {
		return $this->nack();
	}

	/**
	 * Consume job(s) from the queue.
	 *
	 * @return .
	 */
	public function consume($callback, array $options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flag' => AMQP_NOPARAM,
			'return' => false
		);
		$options += $defaults;

		$this->nack();
		$queue = $this->queue();

		return $queue->consume(function($envelope, $queue) use ($callback, &$options) {
			$this->envelope = &$envelope;
			$message = $this->envelope();

			if($result = $callback($message)) {
				$this->ack();
			} else {
				$this->nack();
			}

			if($options['return']) {
				return false;
			}
		}, $options['flag']);
	}

	/**
	 * Initialize AMQPChannel.
	 *
	 * @return .
	 */
	public function channel() {
		if($this->connection) {
			if(!$this->channel) {
				$this->channel = new AMQPChannel($this->connection);
			}
			return $this->channel;
		}
		return false;
	}

	/**
	 * Initialize AMQPExchange.
	 *
	 * @return .
	 */
	public function exchange($options = array()) {
		$config = $this->_config;
		$defaults = array(
			'type' => AMQP_EX_TYPE_DIRECT,
			'flags' => AMQP_DURABLE
		);
		$options = $options + $defaults;
		$channel = $this->channel();

		if($channel) {
			$exchange = $this->exchange;
			if(!$exchange) {
				$exchange = new AMQPExchange($channel);
				$exchange->setName($config['exchange']);
				$exchange->setType($options['type']);
				$exchange->setFlags($options['flags']);
				$exchange->declareExchange();
				$this->exchange = $exchange;
			}
			$this->queue();
			return $exchange;
		}
		return false;
	}

	/**
	 * Initialize AMQPQueue.
	 *
	 * @return .
	 */
	public function queue($options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flags' => AMQP_DURABLE
		);
		$options = $options + $defaults;
		$channel = $this->channel();

		if($channel) {
			$queue = $this->queue;
			if(!$queue instanceof AMQPQueue) {
				$queue = new AMQPQueue($channel);
				$queue->setName($config['queue']);
				$queue->setFlags($options['flags']);
				$queue->declareQueue();
				if($config['exchange'] && $config['routingKey']) {
					$queue->bind($config['exchange'], $config['routingKey']);
				}
				$this->queue = $queue;
			}
			return $queue;
		}
		return false;
	}

	public function envelope(array $options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flag' => ($config['autoAck']) ? AMQP_AUTOACK : 0
		);
		$options = $options + $defaults;
		$envelope = &$this->envelope;

		if(!$envelope instanceof AMQPEnvelope) {
			$queue = $this->queue();
			$envelope = $queue->get($options['flag']);
		}

		return $envelope;
	}

	/**
	 * Acknowledge a message has been processed.
	 *
	 * @return .
	 */
	public function ack($options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flag' => AMQP_NOPARAM
		);
		$options += $defaults;

		if($this->envelope instanceof AMQPEnvelope) {
			$queue = $this->queue();
			$tag = $this->envelope->getDeliveryTag();

			if($queue->ack($tag, $options['flag'])) {
				$this->envelope = null;
				return true;
			}
		}
		return null;
	}

	/**
	 * Unacknowledge a message if it has failed to be processed.
	 *
	 * @return .
	 */
	public function nack($options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flag' => AMQP_REQUEUE
		);
		$options += $defaults;

		if($this->envelope instanceof AMQPEnvelope) {
			$queue = $this->queue();
			$tag = $this->envelope->getDeliveryTag();

			if($queue->nack($tag, $options['flag'])) {
				$this->envelope = null;
				return true;
			}
		}
		return null;
	}

	public function publish($message, array $options = array()) {
		$config = $this->_config;
		$defaults = array(
			'flags' => AMQP_NOPARAM,
			'attributes' => array(
				'content_type' => $message->contentType(),
				'content_encoding' => null,
				'message_id' => null,
				'user_id' => null,
				'app_id' => null,
				'delivery_mode' => 2,
				'priority' => $message->priority(),
				'timestamp' => null,
				'expiration' => null,
				'type' => null,
				'reply_to' => null,
			)
		);
		$options += $defaults;

		$routing_key = $config['routingKey'] ?: $config['queue'];

		$exchange = $this->exchange(array(
			'queue' => $config['queue'],
			'routingKey' => $config['routingKey']
		));

		return $exchange->publish($message->data(), $routing_key, $options['flags'], $options['attributes']);
	}

	/**
	 * Purge queue.
	 *
	 * @return .
	 */
	public function purge() {
		$queue = $this->queue();
		return $queue->purge();
	}

	/**
	 * Determines if the `AMQP` extension has been installed.
	 *
	 * @return boolean Returns `true` if the `AMQP` extension is installed and enabled, `false`
	 *         otherwise.
	 */
	public static function enabled() {
		return extension_loaded('amqp');
	}

	/**
	 * Destructor.
	 *
	 * @return void
	 */
	public function __destruct() {
		$this->disconnect();
	}

}

?>