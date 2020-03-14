<?php

class Logger
{

    const NONE = -1;
    const ERROR = 0;
    const INFO = 1;
    const DEBUG = 2;

    public $logLevel = self::NONE;


	private function showMessage($level, $levelDescription, $message) {
		if ($level <= $this->logLevel) {
			echo $levelDescription.": ".$message."<br>";
		}
	}

	public function error($message) {
		self::showMessage(self::ERROR, "ERROR", $message);
	}

	public function info($message) {
		self::showMessage(self::INFO, 'INFO', $message);
	}

	public function debug($message) {
		self::showMessage(self::DEBUG, "DEBUG", $message);
	}


}

?>