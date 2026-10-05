<?php

echo message::flush();
if (!$this->withChildSelected()) {
	http::redirect('users/');
}
