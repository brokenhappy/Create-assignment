<?php
    /**
     * The content of this file makes creating a table easier
     *
     * PHP version 7.2.10
     *
     * @author     Wout Werkman
     * @see        https://www.WoutWerkman.com
     */

    /**
     * This class creates a table from columns and cell data
     */
    class TableCreator
    {
        
        private $_columns;

        /**
         * Constructs a TableCreater instance with columns.
         *
         * @param array $columns The columns of the table
         */
        public function __construct(array $columns) {
            $this->_columns = $columns;
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

        private function getRowHTML(array $row, bool $isHeader = false): string {
            $result = "<tr>";
            $open = "<" . ($isHeader? "th" : "td") . ">";
            $close = "</" . ($isHeader? "th" : "td") . ">";
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
        public function getHTML(array $rows): string {
            $result = "<table>" . $this->getRowHTML($this->_columns, true);
            foreach ($rows as $row)
                $result .= $this->getRowHTML($row);
            return $result . "</table>";
        }
    }
?>