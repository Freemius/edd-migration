<?php
	/**
	 * @package     Freemius Migration
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Endpoint_Exception extends Exception
	{
		protected $_code;
		protected $_data;

		/**
		 * @param string     $message
		 * @param string     $code
		 * @param int        $httpCode
		 * @param \Exception $previous
		 * @param mixed      $data
		 */
		function __construct(
			$message = '',
			$code = 'error',
			$httpCode = 402,
			\Exception $previous = null,
			$data = false
		) {
			$this->_code = $code;
			$this->_data = $data;
			parent::__construct($message, $httpCode, $previous);
		}

		public function getStringCode()
		{
			return $this->_code;
		}

		public function getType()
		{
			$path_parts = explode('\\', get_called_class());
			$type       = end($path_parts);

			// Drop "Exception".
			return substr($type, 0, strlen($type) - 9);
		}

		public function toArray()
		{
			$arr = array(
				'type'      => $this->getType(),
				'message'   => $this->getMessage(),
				'code'      => $this->getStringCode(),
				'http'      => $this->getCode(),
				'timestamp' => date('r', time()),
			);

			if (false !== $this->_data)
				$arr['data'] = $this->_data;

			return $arr;
		}
	}