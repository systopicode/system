<?php

/**
 * One rendered format of a media record ('original', 'background', …).
 *
 * The properties below were __getPropertyOnce on trait_dynamicProperties.
 * They are read-once hooks now, each with its own backing field.
 *
 * The match had a tail that answered every other name from the underlying
 * file ($format->exists for $format->file->exists). That is gone — reach the
 * file through ->file, which is what the format is a pointer to anyway.
 */
class mediaFormat {

	public $media, $name;

	protected ?string $_ext = null;
	protected ?string $_filename = null;
	protected ?string $_varpath = null;
	protected ?string $_dirpath = null;
	protected ?string $_src = null;
	protected mixed $_href = null;
	protected mixed $_file = null;

	public function __construct($media, $name) {
		$this->media = $media;
		$this->name = $name;
	}

	public string $ext {
		get => $this->_ext ??= $this->getExt();
	}

	public string $filename {
		get => $this->_filename ??= ($this->name === 'original'
				? $this->media->filename
				: $this->media->filename . $this->ext);
	}

	public string $varpath {
		get => $this->_varpath ??= ($this->name === 'original'
				? 'var/original/'
				: "var/formats/$this->name/");
	}

	public string $dirpath {
		get => $this->_dirpath ??= (string) fs::$root . $this->varpath . $this->media->folders;
	}

	public string $src {
		get => $this->_src ??= $this->dirpath . $this->filename;
	}

	public mixed $href {
		get => $this->_href ??= mediaServer::get()->getHref($this);
	}

	public mixed $file {
		get => $this->_file ??= mediaServer::get()->getFile($this);
	}

	function getExt() {
		return ".webp";
	}

	static function create($media, $name) {
		return new static($media, $name);
	}

}
