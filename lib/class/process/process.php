<?php

class process {

	private string $command;
	private ?string $input = null;
	private ?string $stdout = null;
	private ?string $stderr = null;
	private array $pipes = [];
	private $resource;

	public function __construct(string $command) {
		$this->command = $command;
	}

	public function setInput(string $input): void {
		$this->input = $input;
	}

	public function run(): bool {
		$spec = [
			0 => ['pipe', 'r'], // stdin
			1 => ['pipe', 'w'], // stdout
			2 => ['pipe', 'w'], // stderr
		];

		$this->resource = proc_open($this->command, $spec, $this->pipes);

		if (!is_resource($this->resource)) {
			return false;
		}

		// Write input if provided
		if ($this->input !== null) {
			fwrite($this->pipes[0], $this->input);
		}
		fclose($this->pipes[0]);

		// Read output and error
		$this->stdout = stream_get_contents($this->pipes[1]);
		fclose($this->pipes[1]);

		$this->stderr = stream_get_contents($this->pipes[2]);
		fclose($this->pipes[2]);

		proc_close($this->resource);
		return true;
	}

	public function getOutput(): ?string {
		return $this->stdout;
	}

	public function getError(): ?string {
		return $this->stderr;
	}
}
