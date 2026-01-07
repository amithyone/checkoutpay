<?php
/**
 * Redirect to public folder
 * This file is for servers that can't change document root
 */

header('Location: public/');
exit;
