<?php
	/**
	 * This class represents a valid numeric selection by the user 
	 */
	class NumericSelection {
		private $operator;
		private $value;

		/**
		 * @return string: the MySQL operator to be used on $value
		 */
		function get_operator(): string {
			return $this->operator;
		}

		/**
		 * @return int: The integer selected by the user
		 */
		function get_value(): int {
			return $this->value;
		}

		public function __construct(string $operator, int $value) {
			$this->operator = $operator;
			$this->value = $value;
		}
	}

	/**
	 * This class contains a result from generating the SQL
	 */
	class ValuesSQLPair {
		private $sql;
		private $values;

		/**
		 * @return string: The SQL to filter the dataset formatted like:
		 *	WHERE <for each valid row>{ <column> <filter> AND }
		 *	or "" if there is no valid selection
		 */
		function get_sql(): string {
			return $this->sql;
		}

		/**
		 * @return array: The values selected by the user
		 */
		function get_values(): array {
			return $this->values;
		}

		public function __construct(string $sql, array $values) {
			$this->sql = $sql;
			$this->values = $values;
		}
	}

	/**
	 * This class generates filter html, reads and stores the values them from $_POST,
	 * remembers all valid filters from user input and generates SQL from it
	 */
	class QueryFilter {
		
		private $prefix;
		private $numeric_filters;
		private $enumeration_filters;
		private $text_filters;

		/**
		 * @var array $name_to_column_map: maps the filter names to the columns they filter
		 */
		private $name_to_column_map;

		/**
		 * Constructs a QueryFilter instance and sets the prefix
		 *
		 * @param string $prefix The prefix for everything generated in this table
		 */
		public function __construct(string $prefix) {
			$this->prefix = $prefix;
			$this->numeric_filters = array();
			$this->enumeration_filters = array();
			$this->text_filters = array();
			$this->name_to_column_map = array();
		}

		/**
		 * @var array $operators: contains the operators that can be selected for the numeric filter and the MySQL operators they represent.
		 * Also prevents SQL injection
		 */
		private static $operators = array(
			"Greater than" => '>',
			"Greater/equal" => '>=',
			"Equal" => '=',
			"Smaller than" => '<',
			"Smaller/equal" => '<='
		);

		/**
		 * Generates a numeric filter for the user, 
		 * sets the values from last input
		 * and if the values are valid, add it to the $numeric_filters 
		 *
		 * @param string $name The unique name to be shown to the user and to be used in combination with the prefix in $_POST
		 * @param string $column The column in the query that is filtered
		 */
		public function generate_numeric_filter(string $name, string $column) {
			$this->name_to_column_map[$name] = $column;
			// For displaying
			$actual_name = $name;
			// Replace spaces, so its compatible for $_POST
			$name = str_replace(' ', '-', $name);
			// First set the operator selection
			$operator_name = $this->prefix . "operator_" . $name; //the name in $_POST
			// The operator selected by the user
			$selected_operator = (isset($_POST[$operator_name]) && $_POST[$operator_name] !== ""? $_POST[$operator_name] : null);
			
			// Create the select and adds all options for the operators
			echo ucfirst($actual_name) . ': <select name="' . $operator_name . '">';
			foreach (self::$operators as $operator_name => $_) // The key here actually is the name of the option
				echo '<option value="' 
					. $operator_name . '"' 												// The value
					. ($operator_name == $selected_operator ? "selected" : "") . '>' 	// Either if it is selected
					. $operator_name . "</option>";										// The text displayed
			echo "</select>";

			// Then set the numeric input
			$selected_value = null;
			if (isset($_POST[$this->prefix . $name]) && $_POST[$this->prefix . $name] !== "")
				$selected_value = $_POST[$this->prefix . $name];

			echo '<input type="number" '
				. 'name="' . $this->prefix . $name . '" '
				. 'placeholder="' . ucfirst($name) . '"' 
				. ($selected_value !== null?' value="' . $selected_value : "") . '"><br>';

			// If the selection is valid, add it to the $numeric_filters
			if ($selected_operator !== null && $selected_value !== null)
				$this->numeric_filters[$actual_name] = new NumericSelection(self::$operators[$selected_operator], intval($selected_value));
		}

		/**
		 * @return array The selected filters of type (HashMap<string, NumericSelection>)
		 * with keys being the column given and values being the valid numeric filters
		 */
		public function get_numeric_filter_values() : array {
			return $this->numeric_filters;
		}

		/**
		 * Generates an enumeration input for the user
		 * sets the values from last input
		 * and if there was at least one selected value, add it to the $enumeration_filters 
		 *
		 * @param string $name The unique name to be shown to the user and to be used in combination with the prefix and each query result in $_POST
		 * @param string $column The column in the query that is filtered
		 * @param iterable $query_result The query result of which each row, the first result will be used
		 */
		public function generate_enumeration_filter(string $name, string $column, iterable $query_result) {
			$this->name_to_column_map[$name] = $column;
			// For displaying
			$actual_name = $name;
			// Replace spaces, so its compatible for $_POST
			$name = str_replace(' ', '-', $name);
			// To add to the $enumeration_filters map
			$selected_checkboxes = array();

			?>
				<div class="checkbox-group">
					<?= ucfirst($actual_name) . ":"?><input class="select-all" type="checkbox"><br>
					<table>
			<?php
			foreach($query_result as $row) {
				// Replace spaces, so its compatible for $_POST
				$enum_value = str_replace(" ", "-", $row[0]);
				$fullName = $this->prefix . $name . "_" . $enum_value;
				// Either if the filter is enabled for this color
				$checked = isset($_POST[$fullName]) && $_POST[$fullName] === "on" ? "checked" : "";
				if ($checked !== "")
					$selected_checkboxes[] = $enum_value;

				// Add the user input element
				echo '<tr><td>-'.ucfirst($enum_value).'</td><td><input type="checkbox" name="'.$fullName.'" '.$checked.'></td></tr>';
			}
			echo "</table></div>";
			if (count($selected_checkboxes) > 0)
				$this->enumeration_filters[$actual_name] = $selected_checkboxes;
		}

		/**
		 * @return array The selected filters of type (HashMap<string, array<string>>)
		 * with keys being the column given and values being the selected checkboxes
		 * (only those with at least 1 selected value)
		 */
		public function get_enumeration_filter_values() : array {
			return $this->enumeration_filters;
		}

		/**
		 * Generates a text input for the user
		 * sets the values from last input
		 * if there was a not-empty input, add it to the $text_filters array
		 *
		 * @param string $name The unique name to be shown to the user and to be used in combination with the prefix in $_POST
		 */
		public function generate_text_filter(string $name, string $column) {
			$this->name_to_column_map[$name] = $column;
			// For displaying
			$actual_name = $name;
			// Replace spaces, so its compatible for $_POST
			$name = str_replace(' ', '-', $name);
			$value = (isset($_POST[$name]) ? $_POST[$name] : "");
			echo ucfirst($name) . ': <input type="text" '
				. ' name="' . $name . '"'
				. ' placeholder="' . ucfirst($name) . '"'
				. ' value="' . $value . '"><br>';
			if ($value !== "")
				$this->text_filters[$actual_name] = $value;
		}

		/**
		 * @return array The text filters in which the user has inserted data
		 * type HashMap<string, string> with key being the column given and the values being the inserted text
		 */
		public function get_text_filter_values() : array {
			return $this->text_filters;
		}

		public function get_filter_sql() : ValuesSQLPair {
			// The array with parameters of which each value will match with a ?
			$parameters = array();
			// Contains parts of the SQL for filtering
			$filters = array();

			// The order is VERY important for performance
			//ordered on time effeciency (numeric comparison is quickest, then IN() and then LIKE %bla%)

			// Sets the numerical filters (Starts first because its the lightest comparison)
			foreach ($this->get_numeric_filter_values() as $name => $ns) {
				// Format: <column> <operator> <value> example: "height > 150"
				$filters[] = $this->name_to_column_map[$name] . " " . $ns->get_operator() . " ?";
				// Add the value to match the ?
				$parameters[] = $ns->get_value();
			}

			// Sets the enumeration filters
			foreach ($this->get_enumeration_filter_values() as $name => $selected_values) {
				// Format: <column> IN(<values>) example g.gender IN('female', 'n/a')
				$filters[] = $this->name_to_column_map[$name] . " IN(" . implode(",", array_fill(0, count($selected_values), "?")) . ")";
				// Add the values to match the ?s
				$parameters = array_merge($parameters, $selected_values);
			}

			// Sets the text filters
			foreach ($this->get_text_filter_values() as $name => $text) {
				// Format: <column> LIKE <value> example: name LIKE %luke%
				$filters[] = $this->name_to_column_map[$name] . " LIKE ?";
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