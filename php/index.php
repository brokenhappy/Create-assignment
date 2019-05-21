<?php
	include_once "db_connect.php";	
	include_once "table_creator.php";
	include_once "filter_creator.php";

?>
<html>
	<style type="text/css">
		.checkbox-group > * { /* Sets the indentation for checkbox groups */
			margin-left: 2rem;
		}
		.divider {	/* Splits the left and right side */
			display: flex;
		}
		.divider > * { /* Shows the dividing line between the sides */
			border-right: solid black 1px;
		}
	</style>
	<body>
			Filter:
			<form action="index.php" method="post" class="divider">
				<div>
					People:<br>
					<?php

						/*-----------People-----------*/
						$people_filter = new QueryFilter("people_");
						// Generate text filters
						$people_filter->generate_text_filter("name", "`name`");

						// Generate numeric filters
						$people_filter->generate_numeric_filter("height", "height");
						$people_filter->generate_numeric_filter("mass", "mass");
						$people_filter->generate_numeric_filter("birth year", "birth_year");

						$PDO = get_db_connection(); // Open database connection
						// Generate enumeration filters
						$people_filter->generate_enumeration_filter("hair color", "h.color", $PDO->query("SELECT color FROM hair_color"));
						$people_filter->generate_enumeration_filter("skin color", "s.color", $PDO->query("SELECT color FROM skin_color"));
						$people_filter->generate_enumeration_filter("gender", "g.gender", $PDO->query("SELECT gender FROM gender"));
					?>
				</div>
				<div>
					Species:<br>
					<?php
						/*-----------Species-----------*/
						$species_filter = new QueryFilter("species_");
						// Generate text filters
						$species_filter->generate_text_filter("name", "`name`");
						$species_filter->generate_text_filter("language", "language");

						// Generate numeric filters
						$species_filter->generate_numeric_filter("average height", "average_height");
						$species_filter->generate_numeric_filter("average lifespan", "average_lifespan");

						// Generate enumeration filters
						$species_filter->generate_enumeration_filter("hair color", "h.color", $PDO->query("SELECT color FROM hair_color"));
						$species_filter->generate_enumeration_filter("skin color", "sc.color", $PDO->query("SELECT color FROM skin_color"));
						$species_filter->generate_enumeration_filter("eye color", "e.color", $PDO->query("SELECT color FROM eye_color"));
						$species_filter->generate_enumeration_filter("classification", "c.classification", $PDO->query("SELECT classification FROM classification"));
						$species_filter->generate_enumeration_filter("designation", "d.designation", $PDO->query("SELECT designation FROM designation"));
					?>
				</div>
				<input type="submit">
			</form>
		<div class="divider">
			<div>
				People:
				<?php
					$valuesSQLPair = $people_filter->get_filter_sql();

					$stmt = $PDO->prepare(
						"SELECT 
							ID, 
							`name`, 
							IF(height = 0, 'unknown', height), 
							IF(mass = 0, 'unknown', mass), 
							IF(birth_year = 0, 'unknown', birth_year), 
							g.gender,
							s.color,
							h.color
						FROM people p
						INNER JOIN gender g ON g.key = p.gender
						INNER JOIN ct_people_skin_color ct_s ON ct_s.person = ID
						INNER JOIN ct_people_hair_color ct_h ON ct_h.person = ID 
						INNER JOIN skin_color s ON s.`key` = ct_s.color
						INNER JOIN hair_color h ON h.`key` = ct_h.color "
						. $valuesSQLPair->get_sql()
						. "ORDER BY p.name"
					);
					// The prepared sql is executed with the parameters
					$stmt->execute($valuesSQLPair->get_values());

					// The result in a 2D array
					$rows = $stmt->fetchAll();
					// This map will contain all table data for each person, with skin_color and hair_color being an array
					$person_map = array();
					// Convert the multiple rows with the same ID and merges the skin- and hair colors into arrays
					foreach ($rows as $row) {
						if (isset($person_map[$row[0]])) {
							// I chose Arrays over a Sets here because the amounts will never be large enough to get a benifit from the Set
							if (!in_array($row[6], $person_map[$row[0]][5]))
								array_push($person_map[$row[0]][5], $row[6]); // If the array does not have the color yet, add skin color
							if (!in_array($row[7], $person_map[$row[0]][6]))
								array_push($person_map[$row[0]][6], $row[7]); // If the array does not have the color yet, add hair color
						} else {
							$person_map[$row[0]] = array(
								$row[1],		// Name
								$row[2],		// Height
								$row[3],		// Mass
								$row[4],		// Birth year
								$row[5],		// Gender
								array($row[6]),	// Skin color
								array($row[7])	// Hair color
							);
						}
					}

					// Data is ready, table can now be constructed
					$person_table = new TableCreator(array(
						"Name",
						"Height",
						"Mass",
						"Birth year (BBY)",
						"Gender",
						"Skin color",
						"Hair color"
					));
					// Add the table to the document with the people data
					echo $person_table->get_html($person_map);
				?>
			</div>
			<div>
				Species:
				<?php
					$valuesSQLPair = $species_filter->get_filter_sql();

					$stmt = $PDO->prepare(
						"SELECT 
							ID,
							`name`,
							language,
							IF(average_height = 0, 'unknown', average_height),
							IF(average_lifespan = 0, 'unknown', average_lifespan),
							d.designation,
							c.classification,
							sc.color,
							h.color,
							e.color
						FROM species s
						INNER JOIN designation d ON d.`key` = s.designation
						INNER JOIN classification c ON c.`key` = s.classification
						INNER JOIN ct_species_skin_color ct_s ON ct_s.species = ID
						INNER JOIN ct_species_hair_color ct_h ON ct_h.species = ID 
						INNER JOIN ct_species_eye_color ct_e ON ct_e.species = ID 
						INNER JOIN skin_color sc ON sc.`key` = ct_s.color
						INNER JOIN skin_color e ON e.`key` = ct_e.color
						INNER JOIN hair_color h ON h.`key` = ct_h.color "
						. $valuesSQLPair->get_sql()
						. "ORDER BY ID"
					);
					// The prepared sql is executed with the parameters
					$stmt->execute($valuesSQLPair->get_values());
					// The result in a 2D array
					$rows = $stmt->fetchAll();
					// Close the database connection
					$PDO = null;
					// This map will contain all table data for each species, with skin_color and hair_color being an array
					$species_map = array();
					// Convert the multiple rows with the same ID and merges the skin- and hair colors into arrays
					foreach ($rows as $row) {
						$ID = $row[0];
						if (isset($species_map[$row[0]])) {
							// I chose Arrays over a Sets here because the amounts will never be large enough to get a benifit from the Set
							if (!in_array($row[7], $species_map[$ID][6]))
								array_push($species_map[$ID][6], $row[7]);	// If the array does not have the color yet, add skin color
							if (!in_array($row[8], $species_map[$ID][7]))
								array_push($species_map[$ID][7], $row[8]);	// If the array does not have the color yet, add hair color
							if (!in_array($row[9], $species_map[$ID][8]))
								array_push($species_map[$ID][8], $row[9]);	// If the array does not have the color yet, add eye color
						} else {
							$species_map[$row[0]] = array(
								$row[1],		// Name
								$row[2],		// Language
								$row[3],		// Average height
								$row[4],		// Average lifespan
								$row[5],		// Designation
								$row[6],		// Classification
								array($row[7]),	// Skin color
								array($row[8]),	// Hair color
								array($row[9])	// Eye color
							);
						}
					}

					// Data is ready, table can now be constructed
					$species_table = new TableCreator(array(
						"Name",
						"Language",
						"Average height",
						"Average lifespan",
						"Designation",
						"Classification",
						"Skin color",
						"Hair color",
						"Eye color"
					));

					// Add the table to the document with the species data
					echo $species_table->get_html($species_map);
				?>
			</div>
		</div>
	</body>
	<script type="text/javascript">
		window.addEventListener("load", function() {
			var elements = document.querySelectorAll(".checkbox-group > input.select-all");
			for (var i = elements.length - 1; i >= 0; i--) {
				// sets the event for each select-all checkbox for each group
				elements[i].addEventListener("change", function(e) {
					// sets all the checkboxes in the current checkbox-group
					var inputElements = e.target.parentElement.querySelectorAll("input[type=\"checkbox\"]:not(.select-all)");
					var checked = e.target.checked;
					for (var i = inputElements.length - 1; i >= 0; i--)
						inputElements[i].checked = checked;
				});
			}
		});
	</script>
</html>