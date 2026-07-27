<?php
/**
 * Minimal, dependency-free .xlsx (OOXML) writer for a single sheet.
 * Uses inline strings so it never needs a shared-strings table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMG_XLSX_Writer {

	private $rows = array();

	public function add_row( array $cells ) {
		$this->rows[] = array_values( $cells );
	}

	private function column_letter( $index ) {
		$letter = '';
		$index++;
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) ( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private function escape( $text ) {
		return htmlspecialchars( (string) $text, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	private function build_sheet_xml() {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

		foreach ( $this->rows as $r => $cells ) {
			$row_num = $r + 1;
			$xml    .= '<row r="' . $row_num . '">';

			foreach ( $cells as $c => $value ) {
				$ref = $this->column_letter( $c ) . $row_num;

				if ( is_int( $value ) || is_float( $value ) ) {
					$xml .= '<c r="' . $ref . '"><v>' . $this->escape( $value ) . '</v></c>';
				} else {
					$xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->escape( $value ) . '</t></is></c>';
				}
			}

			$xml .= '</row>';
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	/**
	 * @return bool True on success, false if the zip extension is unavailable or the file couldn't be written.
	 */
	public function save( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$zip->addFromString(
			'[Content_Types].xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
			'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
			'<Default Extension="xml" ContentType="application/xml"/>' .
			'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
			'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
			'</Types>'
		);

		$zip->addFromString(
			'_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
			'</Relationships>'
		);

		$zip->addFromString(
			'xl/workbook.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
			'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>' .
			'</workbook>'
		);

		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
			'</Relationships>'
		);

		$zip->addFromString( 'xl/worksheets/sheet1.xml', $this->build_sheet_xml() );

		return $zip->close();
	}
}
