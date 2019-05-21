<?php
	/**
	 * This class creates a table from columns and cell data
	 */
	class TableCreator {
		
		private $columns;

		/**
		 * Constructs a TableCreater instance with columns.
		 *
		 * @param array $columns The columns of the table
		 */
		public function __construct(array $columns) {
			$this->columns = $columns;
		}

		// Practicly the same as json_encode, but arrays and strings are treated slightly differently (And can not be reversed!)
		private function stringify($value) : string {
			if (is_array($value)) {
				// Recursively concatenates all arrays, the array may never contain pointers to itself!
				for ($i = count($value) - 1; $i >= 0; $i--)
					$value[$i] = $this->stringify($value[$i]);
				return implode(", ", $value);
			}
			if (is_object($value))
				return json_encode($value);
			return strval($value); // Strings without quotes
		}

		private function get_row_html(array $row, bool $is_header = false): string {
			$result = "<tr>";
			$open = "<" . ($is_header? "th" : "td") . ">";
			$close = "</" . ($is_header? "th" : "td") . ">";
			foreach ($row as $cell)
				$result .= $open . $this->stringify($cell) . $close;
			return $result . "</tr>";
		}

		/**
		 * Generates the html with given data
		 *
		 * @param array $rows A 2D array with data
		 * @return string The generated HTML
		 */
		public function get_html(array $rows): string {
			$result = "<table>" . $this->get_row_html($this->columns, true);
			foreach ($rows as $row)
				$result .= $this->get_row_html($row);
			return $result . "</table>";
		}
	}
?>