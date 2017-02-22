<?php

class PdfExportRequestHooks {

	public static function onRegistration() {
		global $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;
		$wgLogTypes[]             = 'pdf';
		$wgLogNames  ['pdf']      = 'pdflogpage';
		$wgLogHeaders['pdf']      = 'pdflogpagetext';
		$wgLogActions['pdf/book'] = 'pdflogentry';
	}

	/**
	 * Perform the export operation
	 */
	public static function onUnknownAction( $action, $article ) {
		global $wgOut, $wgUser, $wgParser, $wgRequest, $wgAjaxComments, $wgPdfBookDownload;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript;

		if( $action == 'pdfexport' ) {
			$title = $article->getTitle();
			$book = $title->getText();
			$opt = ParserOptions::newFromUser( $wgUser );

			// Log the export
			$msg = wfMessage( 'pdfbook-log', $wgUser->getUserPage()->getPrefixedText() )->text();
			$log = new LogPage( 'pdf', false );
			$log->addEntry( 'book', $article->getTitle(), $msg );

			$options = self::getOptions();

			$tmpFile = $wgUploadDirectory . '/pdfexport-tmp-' . md5( $title->getFullURL() );
			$cacheFile = $wgUploadDirectory . '/pdfexport-cache-' . md5( $title->getFullURL() );

			self::convertToPdfWithWkhtmltopdf($title->getFullURL(), $cacheFile, $options);

			$book = 'Tutoriel';

			$wgOut->disable();
			header( "Content-Type: application/pdf" );
			if( $wgPdfBookDownload ) {
				header( "Content-Disposition: attachment; filename=\"$book.pdf\"" );
			} else {
				header( "Content-Disposition: inline; filename=\"$book.pdf\"" );
			}

			readfile( $cacheFile );

			return false;
		}
		return true;
	}

	private static function getOptions() {
		return [
				'left' => 10,
				'right' => 10,
				'top' => 10,
				'bottom' => 10
		];
	}



	private static function convertToPdfWithWkhtmltopdf($htmlFile, $outputFile, $options) {

		// big fake :
		// call wkhtmltopdf with url of the page (will do https requests to get the page)

		$cmd  = "-L {$options['left']} -R {$options['right']} -T {$options['top']} -B {$options['bottom']}";


		// this do not work ?
		$cmd  = "$cmd --footer-right \"Page [page] / [toPage]\"";

		// Build the htmldoc command
		$cmd  = "xvfb-run /usr/bin/wkhtmltopdf $cmd \"$htmlFile\" \"$outputFile\"";

		var_dump($cmd);die();

		// Execute the command outputting to the cache file
		exec( "$cmd", $output, $result );

	}


	/**
	 * Return a sanitised property for htmldoc using global, request or passed default
	 */
	private static function setProperty( $name, $val, $prefix = 'pdf' ) {
		global $wgRequest;
		if( $wgRequest->getText( "$prefix$name" ) ) $val = $wgRequest->getText( "$prefix$name" );
		if( $wgRequest->getText( "amp;$prefix$name" ) ) $val = $wgRequest->getText( "amp;$prefix$name" ); // hack to handle ampersand entities in URL
		if( isset( $GLOBALS["wgPdfBook$name"] ) ) $val = $GLOBALS["wgPdfBook$name"];
		return preg_replace( '|[^-_:.a-z0-9]|i', '', $val );
	}

	/**
	 * Add PDF to actions tabs in MonoBook based skins
	 */
	public static function onSkinTemplateTabs( $skin, &$actions) {
		global $wgPdfBookTab, $wgUser;
		if( $wgPdfBookTab && $wgUser->isLoggedIn() ) {
			$actions['pdfbook'] = array(
				'class' => false,
				'text' => wfMessage( 'pdfbook-action' )->text(),
				'href' => self::actionLink( $skin )
			);
		}
		return true;
	}

	/**
	 * Add PDF to actions tabs in vector based skins
	 */
	public static function onSkinTemplateNavigation( $skin, &$actions ) {
		global $wgPdfBookTab, $wgUser;
		if( $wgPdfBookTab && $wgUser->isLoggedIn() ) {
			$actions['views']['pdfbook'] = array(
				'class' => false,
				'text' => wfMessage( 'pdfbook-action' )->text(),
				'href' => self::actionLink( $skin )
			);
		}
		return true;
	}

	/**
	 * Get the URL for the action link
	 */
	public static function actionLink( $skin ) {
		$qs = 'action=pdfexport&format=single';
		foreach( $_REQUEST as $k => $v ) if( $k != 'title' ) $qs .= "&$k=$v";
		return $skin->getTitle()->getLocalURL( $qs );
	}
}
