<?php

// An open user lightbox flushes the message itself. Flushing it here would
// hide the confirmation behind the dialog.
$editingUser = (string) (\http::get('user_id') ?: \http::dataset('user_id') ?: '');
if ($editingUser === '') {
	echo message::flush();
}
$this->withChildSelected();
