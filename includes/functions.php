<?php

function xml_response( $xml ) {
	header( 'Content-Type: text/xml' );

	$dom = new \DOMDocument( '1.0' );

	$dom->loadXML( $xml->asXML(), LIBXML_NOBLANKS );
	$dom->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	echo $dom->saveXML(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
