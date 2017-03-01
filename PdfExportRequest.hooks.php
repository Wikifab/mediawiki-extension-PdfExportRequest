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
	 * Parser function to insert a link to pdf.
	 */
	public static function parserInit( $parser ) {
		$parser->setFunctionHook( 'pdfExportButton', array('PdfExportRequestHooks', 'addButtonParser' ));
		return true;
	}



	public static function addButtonParser( $input, $type = 'top', $number = 4 ) {

		$out = '<button class="pdfExportButton">';
		$out .= '</button>';


		return array( $out, 'noparse' => true, 'isHTML' => true );
	}

	/**
	 * Perform the export operation
	 */
	public static function onUnknownAction( $action, $article ) {
		global $wgOut, $wgUser, $wgRequest, $wgPdfExportRequestDownload;
		global $wgUploadDirectory;

		if( $action == 'pdfexport' ) {
			$title = $article->getTitle();
			$filename = 'Wikifab-' . $title->getText();

			$options = self::getOptions();

			$cacheFile = $wgUploadDirectory . '/pdfexport-cache-' . md5( $title->getFullURL() );

			// check cache File
			$needReload = true;
			if (file_exists($cacheFile)) {
				// last edit timestamp :
				$timestamp = $article->getTimestamp();
				// cacheFile timestamp :
				$fileTimeStamp = date ("YmdHis", filemtime($cacheFile));
				$needReload = $timestamp > $fileTimeStamp;
			}
			// generate file if cache file not valid
			if( ! file_exists($cacheFile) || $needReload) {
				self::convertToPdfWithWkhtmltopdf($title->getFullURL(), $cacheFile, $options);
			}

			if(file_exists($cacheFile)) {
				$wgOut->disable();
				header( "Content-Type: application/pdf" );
				if( $wgPdfExportRequestDownload ) {
					header( "Content-Disposition: attachment; filename=\"$filename.pdf\"" );
				} else {
					header( "Content-Disposition: inline; filename=\"$filename.pdf\"" );
				}
				readfile( $cacheFile );
			}

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

		// call wkhtmltopdf with url of the page (will do https requests to get the page)
		$cmd  = "-L {$options['left']} -R {$options['right']} -T {$options['top']} -B {$options['bottom']}";

		// this do not work with current version of wkhtmltopdf
		//$cmd  = "$cmd --footer-right \"Page [page] / [toPage]\"";

		// Build the htmldoc command
		$cmd  = "xvfb-run /usr/bin/wkhtmltopdf $cmd \"$htmlFile\" \"$outputFile\"";

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
		global $wgPdfExportRequestTab;

		if($wgPdfExportRequestTab) {
			$actions['views']['pdfexport'] = array(
					'class' => false,
					'text' => wfMessage( 'pdfexportrequest-print' )->text(),
					'href' => self::actionLink( $skin )
			);
		}
		return true;
	}

	/**
	 * Add PDF to actions tabs in vector based skins
	 */
	public static function onSkinTemplateNavigation( $skin, &$actions ) {
		global $wgPdfExportRequestTab;

		if($wgPdfExportRequestTab) {
			$actions['views']['pdfexport'] = array(
				'class' => false,
				'text' => wfMessage( 'pdfexportrequest-print' )->text(),
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
