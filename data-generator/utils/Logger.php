<?php

class Logger
{

    const NONE = -1;
    const ERROR = 0;
    const INFO = 1;
    const DEBUG = 2;

	private $logLevel = self::NONE;


	private function showMessage($level, $levelDescription, $message) {
		if ($level <= $this->logLevel) {
			echo $levelDescription.": ".$message."<br>";
		}
	}

	public function error($message) {
		$this->showMessage(self::ERROR, "ERROR", $message);
	}

	public function info($message) {
		$this->showMessage(self::INFO, 'INFO', $message);
	}

	public function debug($message) {
		$this->showMessage(self::DEBUG, "DEBUG", $message);
	}

	public function setLogLevel($level) {
		$this->logLevel = $level;
	}

}

?>