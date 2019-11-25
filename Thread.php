<?php

namespace FunnyFig\Swoole;

use chan, co;

abstract class Thread {

	protected $id = -1;
	protected $ichan;

	function __construct(int $nqueue=1)
	{
		$this->launch($nqueue);
	}

	function launch(int $nqueue=1)
	{
		if ($this->is_alive()) return;
		if ($this->ichan) {
			$nqueue = $this->ichan->capacity;
			$this->ichan->close();
		}
		$this->ichan = new chan($nqueue);
		$this->id = go([$this, '_proc']);
	}

	protected function _proc()
	{
		$this->proc();
		$this->id = -1;
	}

	abstract protected function proc();

	function is_alive()
	{
		return co::exists($this->id);
	}

	protected function invoke($cmd, ...$args)
	{
		if (!$this->is_alive()) return;

		if (co::getuid() >= 0) {
			$this->ichan->push([$cmd, $args]);
		}
		else {
			go(function ($cmd, $args) {
				$this->ichan->push([$cmd, $args]);
			}, $cmd, $args);
		}
	}

	protected function has_cmd()
	{
		return !$this->ichan->isEmpty();
	}

	protected function get_cmd($ms = 0)
	{
		return $this->ichan->pop($ms/1000);
	}

}


//------------------------------------------------------------------------------
if (!debug_backtrace()) {

class Test extends Thread {
	protected function proc()
	{
		for (;;) {
			list($cmd, $args) = $this->get_cmd();
			$chan = array_pop($args);
			$rv = $cmd(...$args);
			$chan->push($rv);
		}
	}

	function execute(callable $foo, ...$args) {
		go(function ($foo, $args) {
			$chan = new chan(1);
			$args[] = $chan;
			$this->invoke($foo, ...$args);
			echo $chan->pop()."\n";
		}, $foo, $args);
	}
}

$t = new Test();

$t->execute(function(...$args) {
	return "proc1: ".join(', ', $args);
}, 1, 2, 3, 4);

$t->execute(function(...$args) {
	return "proc2: ".array_sum($args);
}, 1, 2, 3, 4);


}
