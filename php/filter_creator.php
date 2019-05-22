<?php
    /**
     * This file contains the QueryFilter class and its dependencies
     *
     * The content of this file makes it easier to let the user select a
     * filter for a query.
     *
     * PHP version 7.2.10
     *
     * @author     Wout Werkman
     * @see        https://www.WoutWerkman.com
     */

    /**
     * This class represents a valid numeric selection by the user 
     */
    class NumericSelection 
    {
        private $_operator;
        private $_value;

        /**
         * @return string: the MySQL operator to be used on $value
         */
        function getOperator(): string {
            return $this->_operator;
        }

        /**
         * @return int: The integer selected by the user
         */
        function getValue(): int {
            return $this->_value;
        }

        public function __construct(string $operator, int $value) {
            $this->_operator = $operator;
            $this->_value    = $value;
        }
    }

    /**
     * This class contains a result from generating the SQL
     */
    class ValuesSQLPair 
    {
        private $_sql;
        private $_values;

        /**
         * @return string: The SQL to filter the dataset formatted like:
         *  WHERE <for each valid row>{ <column> <filter> AND }
         *  or "" if there is no valid selection
         */
        function getSQL(): string {
            return $this->_sql;
        }

        /**
         * @return array: The values selected by the user
         */
        function getValues(): array {
            return $this->_values;
        }

        public function __construct(string $sql, array $values) {
            $this->_sql    = $sql;
            $this->_values = $values;
        }
    }

    /**
     * This class generates filter html, reads and stores the values them from $_POST,
     * remembers all valid filters from user input and generates SQL from it
     */
    class QueryFilter 
    {
        
        private $_prefix;
        private $_numericFilters;
        private $_enumerationFilters;
        private $_textFilters;

        /**
         * @var array $_nameToColumnMap: maps the filter names to the columns they filter
         */
        private $_nameToColumnMap;

        /**
         * Constructs a QueryFilter instance and sets the prefix
         *
         * @param string $prefix The prefix for everything generated in this table
         */
        public function __construct(string $prefix) {
            $this->_prefix             = $prefix;
            $this->_numericFilters     = array();
            $this->_enumerationFilters = array();
            $this->_textFilters        = array();
            $this->_nameToColumnMap    = array();
        }

        /**
         * @var array $operators: contains the operators that can be selected for the numeric filter and the MySQL operators they represent.
         * Also prevents SQL injection
         */
        private static $_operators = array(
            "Greater than" => '>',
            "Greater/equal" => '>=',
            "Equal" => '=',
            "Smaller than" => '<',
            "Smaller/equal" => '<='
        );

        /**
         * Generates a numeric filter for the user, 
         * sets the values from last input
         * and if the values are valid, add it to the $numericFilters 
         *
         * @param string $name The unique name to be shown to the user and to be used in combination with the prefix in $_POST
         * @param string $column The column in the query that is filtered
         */
        public function generateNumericFilter(string $name, string $column) {
            $this->_nameToColumnMap[$name] = $column;
            // For displaying
            $actualName       = $name;
            // Replace spaces, so its compatible for $_POST
            $name             = str_replace(' ', '-', $name);
            // First set the operator selection
            $operatorName     = $this->_prefix . "operator_" . $name; // The name in $_POST
            // The operator selected by the user
            $selectedOperator = (isset($_POST[$operatorName]) && $_POST[$operatorName] !== ""? $_POST[$operatorName] : null);
            
            // Create the select and adds all options for the operators
            echo ucfirst($actualName) . ': <select name="' . $operatorName . '">';
            foreach (self::$_operators as $operatorName => $_) // The key here actually is the name of the option
                echo '<option value="' 
                    . $operatorName . '"'                                               // The value
                    . ($operatorName == $selectedOperator ? "selected" : "") . '>'  // Either if it is selected
                    . $operatorName . "</option>";                                      // The text displayed
            echo "</select>";

            // Then set the numeric input
            $selectedValue = null;
            if (isset($_POST[$this->_prefix . $name]) && $_POST[$this->_prefix . $name] !== "")
                $selectedValue = $_POST[$this->_prefix . $name];

            echo '<input type="number" '
                . 'name="' . $this->_prefix . $name . '" '
                . 'placeholder="' . ucfirst($name) . '"' 
                . ($selectedValue !== null?' value="' . $selectedValue : "") . '"><br>';

            // If the selection is valid, add it to the $numericFilters
            if ($selectedOperator !== null && $selectedValue !== null)
                $this->_numericFilters[$actualName] = new NumericSelection(self::$_operators[$selectedOperator], intval($selectedValue));
        }

        /**
         * @return array The selected filters of type (HashMap<string, NumericSelection>)
         * with keys being the column given and values being the valid numeric filters
         */
        public function getNumericFilterValues() : array {
            return $this->_numericFilters;
        }

        /**
         * Generates an enumeration input for the user
         * sets the values from last input
         * and if there was at least one selected value, add it to the $enumerationFilters 
         *
         * @param string $name The unique name to be shown to the user and to be used in combination with the prefix and each query result in $_POST
         * @param string $column The column in the query that is filtered
         * @param iterable $queryResult The query result of which each row, the first result will be used
         */
        public function generateEnumerationFilter(string $name, string $column, iterable $queryResult) {
            $this->_nameToColumnMap[$name] = $column;
            // For displaying
            $actualName         = $name;
            // Replace spaces, so its compatible for $_POST
            $name               = str_replace(' ', '-', $name);
            // To add to the $enumerationFilters map
            $selectedCheckboxes = array();

            ?>
                <div class="checkbox-group">
                    <?= ucfirst($actualName) . ":"?><input class="select-all" type="checkbox"><br>
                    <table>
            <?php
            foreach($queryResult as $row) {
                // Replace spaces, so its compatible for $_POST
                $enumValue = str_replace(" ", "-", $row[0]);
                $fullName  = $this->_prefix . $name . "_" . $enumValue;
                // Either if the filter is enabled for this color
                $checked   = isset($_POST[$fullName]) && $_POST[$fullName] === "on" ? "checked" : "";
                if ($checked !== "")
                    $selectedCheckboxes[] = $enumValue;

                // Add the user input element
                echo '<tr><td>-'.ucfirst($enumValue).'</td><td><input type="checkbox" name="'.$fullName.'" '.$checked.'></td></tr>';
            }
            echo "</table></div>";
            if (count($selectedCheckboxes) > 0)
                $this->_enumerationFilters[$actualName] = $selectedCheckboxes;
        }

        /**
         * @return array The selected filters of type (HashMap<string, array<string>>)
         * with keys being the column given and values being the selected checkboxes
         * (only those with at least 1 selected value)
         */
        public function getEnumerationFilterValues() : array {
            return $this->_enumerationFilters;
        }

        /**
         * Generates a text input for the user
         * sets the values from last input
         * if there was a not-empty input, add it to the $textFilters array
         *
         * @param string $name The unique name to be shown to the user and to be used in combination with the prefix in $_POST
         */
        public function generateTextFilter(string $name, string $column) {
            $this->_nameToColumnMap[$name] = $column;
            // For displaying
            $actualName = $name;
            // Replace spaces, so its compatible for $_POST
            $name = str_replace(' ', '-', $name);
            $value = (isset($_POST[$name]) ? $_POST[$name] : "");
            echo ucfirst($name) . ': <input type="text" '
                . ' name="' . $name . '"'
                . ' placeholder="' . ucfirst($name) . '"'
                . ' value="' . $value . '"><br>';
            if ($value !== "")
                $this->_textFilters[$actualName] = $value;
        }

        /**
         * @return array The text filters in which the user has inserted data
         * type HashMap<string, string> with key being the column given and the values being the inserted text
         */
        public function getTextFilterValues() : array {
            return $this->_textFilters;
        }

        public function getFilterSQL() : ValuesSQLPair {
            // The array with parameters of which each value will match with a ?
            $parameters = array();
            // Contains parts of the SQL for filtering
            $filters = array();

            // The order is VERY important for performance
            // Ordered on time effeciency (numeric comparison is quickest, then IN() and then LIKE %bla%)

            // Sets the numerical filters (Starts first because its the lightest comparison)
            foreach ($this->getNumericFilterValues() as $name => $ns) {
                // Format: <column> <operator> <value> example: "height > 150"
                $filters[] = $this->_nameToColumnMap[$name] . " " . $ns->getOperator() . " ?";
                // Add the value to match the ?
                $parameters[] = $ns->getValue();
            }

            // Sets the enumeration filters
            foreach ($this->getEnumerationFilterValues() as $name => $selectedValues) {
                // Format: <column> IN(<values>) example g.gender IN('female', 'n/a')
                $filters[] = 
                    $this->_nameToColumnMap[$name]
                    . " IN(" 
                        . implode(",", array_fill(0, count($selectedValues), "?")) 
                    . ")";
                // Add the values to match the ?s
                $parameters = array_merge($parameters, $selectedValues);
            }

            // Sets the text filters
            foreach ($this->getTextFilterValues() as $name => $text) {
                // Format: <column> LIKE <value> example: name LIKE %luke%
                $filters[] = $this->_nameToColumnMap[$name] . " LIKE ?";
                // Escape existing % and add %s for searching
                $text = "%" . str_replace("%", "\\%", $text) . "%";
                // Add the text to match the ?
                $parameters[] = $text;
            }

            // Return the filter SQL and parameters
            if (count($filters) === 0)
                return new ValuesSQLPair("", array());
            return new ValuesSQLPair("WHERE " . implode(" AND ", $filters) . " ", $parameters);

        }
    }

?>