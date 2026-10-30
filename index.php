<?php
/**
 * VerityVote - Root Dispatcher Redirect
 * Redirects incoming root requests to the public document root.
 */
header('Location: public/');
exit;
