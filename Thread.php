<?php

namespace FunnyFig\Swoole;

use chan, co;

abstract class Thread {

	protected $id = -1;
	protected $chan;

	function __construct(callable $proc=null)
	{
		$this->chan = new chan(1);
		$this->id = go([$this, '_proc'], $proc);
	}

	protected function _proc(callable $proc=null)
	{
		$this->proc($proc);
		$this->id = -1;
	}

	abstract protected function proc(callable $proc);

	function is_alive()
	{
		return co::exists($this->id);
	}

	protected function invoke($cmd, ...$args)
	{
		if (!$this->is_alive()) return;

		if (co::getuid() >= 0) {
			$this->chan->push([$cmd, $args]);
		}
		else {
			go(function ($cmd, $args) {
				$this->chan->push([$cmd, $args]);
			}, $cmd, $args);
		}
	}

	protected function has_cmd()
	{
		return !$this->chan->isEmpty();
	}

	protected function get_cmd($ms = 0)
	{
		return $this->chan->pop($ms/1000);
	}

}


//------------------------------------------------------------------------------
if (!debug_backtrace()) {

class Test extends Thread {
	//protected const STOP = 0;
	//protected const START = 1;

	protected function proc(callable $proc=null)
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

$t->execute(function() {
	return "proc";
});

}
