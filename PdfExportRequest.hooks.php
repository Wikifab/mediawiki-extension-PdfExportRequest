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
		global $wgUploadDirectory, $wgPdfExportErrorLog;
		global $wfPdfExportPrefix, $wgSitename;

		if( ! isset($wfPdfExportPrefix)) {
			$wfPdfExportPrefix = $wgSitename;
		}

		if( $action == 'pdfexport' ) {

			if (!$wgUser->isAllowed('exportpdf')) {
                throw new PermissionsError( 'exportpdf' );
            }

			$title = $article->getTitle();
			$filename = $wfPdfExportPrefix . '-' . $title->getText();

			$options = self::getOptions();

			$exportDir = $wgUploadDirectory . '/pdfexport';
			if (!file_exists($exportDir)) {
				mkdir($exportDir, 0777, true);
			}
			$cacheFile = $exportDir . '/cache-' . md5( $title->getFullURL() );

			// check cache File
			$needReload = true;
			if (file_exists($cacheFile)) {
				// last edit timestamp :
				$timestamp = wfTimestamp( TS_MW, $article->getTimestamp());
				// cacheFile timestamp : (must be in same format as $article->getTimestamp())
				$fileTimeStamp = wfTimestamp( TS_MW, filemtime($cacheFile) );
				$needReload = $timestamp > $fileTimeStamp;
			}
			// generate file if cache file not valid
			if( ! file_exists($cacheFile) || $needReload) {
				$convertResult = self::convertToPdfWithWkhtmltopdf($title->getFullURL(), $cacheFile, $options);
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
			} else if ($wgPdfExportErrorLog) {
				wfErrorLog( "Error in PDF generation for $filename", $wgPdfExportErrorLog );
				wfErrorLog( "Error cmd " . $convertResult['cmd'], $wgPdfExportErrorLog );
				wfErrorLog( "Error result " . $convertResult['output'], $wgPdfExportErrorLog );
			}

			return false;
		}
		return true;
	}

	private static function getOptions() {
		global $wgPdfExportRequestWkhtmltopdfParams;
		global $wgPdfExportRequestWkhtmltopdfReplaceHostname;
		global $wgPdfExportRequestHeaderFile, $wgPdfExportRequestFooterFile;

		if ( $wgPdfExportRequestFooterFile == 'default') {
			$wgPdfExportRequestFooterFile = __DIR__ . '/templates/footer.html';
		}
		$opt = [
				'left' => 10,
				'right' => 10,
				'top' => 10,
				'bottom' => 10
		];
		if ($wgPdfExportRequestWkhtmltopdfReplaceHostname) {
			$opt['replaceHostname'] = $wgPdfExportRequestWkhtmltopdfReplaceHostname;
		}
		if ($wgPdfExportRequestWkhtmltopdfParams) {
			$opt['customsparams'] = $wgPdfExportRequestWkhtmltopdfParams;
		}
		if ($wgPdfExportRequestHeaderFile) {
			$opt['header-html'] = $wgPdfExportRequestHeaderFile;
		}
		if ($wgPdfExportRequestFooterFile) {
			$opt['footer-html'] = $wgPdfExportRequestFooterFile;
		}
		return $opt;
	}



	private static function convertToPdfWithWkhtmltopdf($htmlFile, $outputFile, $options) {

		global $wgServer, $wgPberWkhtmlToPdfExec;

		if (! isset($wgPberWkhtmlToPdfExec)) {
			$wkHtmlExec = "xvfb-run /usr/bin/wkhtmltopdf";
		} else {
			$wkHtmlExec = $wgPberWkhtmlToPdfExec;
		}

		// call wkhtmltopdf with url of the page (will do https requests to get the page)
		$cmd  = "-L {$options['left']} -R {$options['right']} -T {$options['top']} -B {$options['bottom']} --print-media-type ";

		// this do not work with current version of wkhtmltopdf
		//$cmd  = "$cmd --footer-right \"Page [page] / [toPage]\"";
		//$headerFile = dirname(__FILE__) . '/templates/header.html';
		//$footerFile = dirname(__FILE__) . '/templates/footer.html';
		//$cmd  = "$cmd --header-html \"$headerFile\" ";
		//$cmd  = "$cmd --footer-html \"$footerFile\" ";

		if (isset($options['customsparams'])) {
			$cmd  = "$cmd {$options['customsparams']}";
		}
		if (isset($options['header-html'])) {
			$cmd  = "$cmd --header-html {$options['header-html']}";
		}
		if (isset($options['footer-html'])) {
			$cmd  = "$cmd --footer-html {$options['footer-html']}";
		}
		if (isset($options['replaceHostname'])) {
			$htmlFile = str_replace($wgServer, $options['replaceHostname'], $htmlFile);
		}
		// Build the htmldoc command
		$cmd  = "$wkHtmlExec --javascript-delay 2000 $cmd \"$htmlFile\" \"$outputFile\"";

		// Execute the command outputting to the cache file
		exec( "$cmd", $output, $result );
		return array('cmd' => $cmd, 'output' => $output,'result' => $result);
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

		$user = $skin->getUser();

		if($wgPdfExportRequestTab && $user->isAllowed( 'exportpdf' )) {
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

		$user = $skin->getUser();

		if($wgPdfExportRequestTab && $user->isAllowed( 'exportpdf' )) {
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
		// removed to avoir Notice: Array to string conversion in /var/www/preprod.wikifab.org/extensions/PdfExportRequest/PdfExportRequest.hooks.php on line 190
		// Be should be added back to be able to print pdf of older versions
		//foreach( $_REQUEST as $k => $v ) if( $k != 'title' ) $qs .= "&$k=$v";
		return $skin->getTitle()->getLocalURL( $qs );
	}
}
