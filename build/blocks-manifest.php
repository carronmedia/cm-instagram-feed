<?php
// This file is generated. Do not modify it manually.
return array(
	'cm-instagram-feed' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'carronmedia/cm-instagram-feed',
		'version' => '1.0.0',
		'title' => 'CM Instagram Feed',
		'category' => 'widgets',
		'icon' => 'instagram',
		'description' => 'Display your Instagram posts in a grid with mobile carousel.',
		'attributes' => array(
			'showCaption' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'anchor' => true
		),
		'render' => 'file:./template.php',
		'textdomain' => 'cm-instagram-feed',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js'
	)
);
