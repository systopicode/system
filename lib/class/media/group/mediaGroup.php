<?php

/**
 * A named group of media on a page ('main', 'gallery', …).
 *
 * first / random / shuffle / all were __get cases; they are hooks now.
 * __get stays for everything else: the remaining names are collection keys,
 * which objCollection::__get resolves through offsetGet().
 */
#[AllowDynamicProperties]
class mediaGroup extends objCollection {

	public mixed $first {
		get => $this->count() ? $this->reset() : NULL;
	}

	public mixed $random {
		get => $this->count() ? $this[rand(0, $this->count() - 1)] : NULL;
	}

	/** alias of random */
	public mixed $shuffle {
		get => $this->random;
	}

	public mixed $all {
		get => $this;
	}
}
