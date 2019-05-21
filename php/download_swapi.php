<?php
	include_once "db_connect.php";

	/**
	 * Downloads all objects of a type available on swapi.co
	 *
	 * @param string $type The type to download can be {"films", "people", "planets", "species", "starships", "vehicles"}
	 * @return array An array containing all the objects found on swapi, parsed using JSON
	 */
	function download_swapi_list(string $type) : array {
		//download initial page
		$content = json_decode(file_get_contents("http://swapi.co/api/" . $type . "?format=json"));
		$count = 0;
		$result = array_fill(0, $content->count, null); //instantiate array of content size
		while (true) {
			foreach ($content->results as $content_result) //iterate over every result from the content
				$result[$count++] = $content_result; //add the content to the result

			if (!isset($content->next))
				return $result;	//return the results if there is no next page
			$content = json_decode(file_get_contents($content->next)); //otherwise, download next page
		}
	}

	//I know parameterised queries arent exactly necessary, but I still use them as they are a good habit

	$PDO = get_db_connection(); //connect to database

	//clear data
	$PDO->query(
		"TRUNCATE `create_opdracht`.`ct_people_hair_color`;
		TRUNCATE `create_opdracht`.`ct_people_skin_color`;
		TRUNCATE `create_opdracht`.`ct_species_hair_color`;
		TRUNCATE `create_opdracht`.`ct_species_skin_color`;
		TRUNCATE `create_opdracht`.`ct_species_eye_color`;
		SET SQL_SAFE_UPDATES = 0;
		DELETE FROM `create_opdracht`.`people` WHERE 1 = 1;
		DELETE FROM `create_opdracht`.`species` WHERE 1 = 1;
		SET SQL_SAFE_UPDATES = 1;"
	);

	//download all people and iterate over them
	foreach (download_swapi_list("people") as $index => $person) {
		$ID = $index + 1;

		//removing BBY from birth_date and parsing to int
		$birth_year = null;
		if ($person->birth_year != "unknown")
			$birth_year = intval(substr($person->birth_year, 0, strlen($person->birth_year) - 3));
		$mass = $person->mass == 0 ? null : $person->mass;
		$height = $person->height == 0 ? null : $person->height;

		//insert the person
		$stmt = $PDO->prepare(
			"INSERT INTO `create_opdracht`.`people` (
				`ID`,
				`name`,
				`height`,
				`mass`,
				`birth_year`,
				`gender`
			) VALUES (
				:ID,
				:name,
				:height,
				:mass,
				:birth_year,
				(SELECT `key` FROM gender WHERE gender = :gender)
			);"
		);
		$stmt->bindParam(":ID", $ID, PDO::PARAM_INT);
		$stmt->bindParam(":name", $person->name, PDO::PARAM_STR);
		$stmt->bindParam(":height", $height, PDO::PARAM_INT);
		$stmt->bindParam(":mass", $mass, PDO::PARAM_INT);
		$stmt->bindParam(":birth_year", $birth_year, PDO::PARAM_INT);
		$stmt->bindParam(":gender", $person->gender, PDO::PARAM_STR);
		
		$stmt->execute();
		
		//insert all people's hair colors
		foreach (explode(',', $person->hair_color) as $value) {
			$hair_color = trim($value);

			$stmt = $PDO->prepare(
				"INSERT INTO `create_opdracht`.`ct_people_hair_color` (
					`person`,
					`color`
				) VALUES (
					:personID,
					(SELECT `key` FROM hair_color WHERE color = :hair_color)
				);"
			);
			$stmt->bindParam(':personID', $ID, PDO::PARAM_INT);
			$stmt->bindParam(':hair_color', $hair_color, PDO::PARAM_STR);
			$stmt->execute();
		}
		//insert all people's skin colors
		foreach (explode(',', $person->skin_color) as $value) {
			$skin_color = trim($value);

			$stmt = $PDO->prepare(
				"INSERT INTO `create_opdracht`.`ct_people_skin_color` (
					`person`,
					`color`
				) VALUES (
					:personID,
					(SELECT `key` FROM skin_color WHERE color = :skin_color)
				);"
			);
			$stmt->bindParam(':personID', $ID, PDO::PARAM_INT);
			$stmt->bindParam(':skin_color', $skin_color, PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	foreach (download_swapi_list("species") as $index => $species) {
		$ID = $index + 1;

		$average_height = $species->average_height == 0 ? null : $species->average_height;
		$average_lifespan = $species->average_lifespan == 0 ? null : $species->average_lifespan;

		//insert the species
		$stmt = $PDO->prepare(
			"INSERT INTO `create_opdracht`.`species` (
				`ID`,
				`name`,
				`classification`,
				`designation`,
				`average_height`,
				`average_lifespan`,
				`language`
			) VALUES (
				:ID,
				:name,
				(SELECT `key` FROM classification WHERE classification = :classification),
				(SELECT `key` FROM designation WHERE designation = :designation),
				:average_height,
				:average_lifespan,
				:language
			);"
		);
		$stmt->bindParam(":ID", $ID, PDO::PARAM_INT);
		$stmt->bindParam(":name", $species->name, PDO::PARAM_STR);
		$stmt->bindParam(":classification", $species->classification, PDO::PARAM_STR);
		$stmt->bindParam(":designation", $species->designation, PDO::PARAM_STR);
		$stmt->bindParam(":average_height", $average_height, PDO::PARAM_INT);
		$stmt->bindParam(":average_lifespan", $average_lifespan, PDO::PARAM_INT);
		$stmt->bindParam(":language", $species->language, PDO::PARAM_STR);
		
		$stmt->execute();
		
		//insert all species' hair colors
		foreach (explode(',', $species->hair_colors) as $value) {
			$hair_color = trim($value);

			$stmt = $PDO->prepare(
				"INSERT INTO `create_opdracht`.`ct_species_hair_color` (
					`species`,
					`color`
				) VALUES (
					:speciesID,
					(SELECT `key` FROM hair_color WHERE color = :hair_color)
				);"
			);
			$stmt->bindParam(':speciesID', $ID, PDO::PARAM_INT);
			$stmt->bindParam(':hair_color', $hair_color, PDO::PARAM_STR);
			$stmt->execute();
		}
		//insert all species' skin colors
		foreach (explode(',', $species->skin_colors) as $value) {
			$skin_color = trim($value);

			$stmt = $PDO->prepare(
				"INSERT INTO `create_opdracht`.`ct_species_skin_color` (
					`species`,
					`color`
				) VALUES (
					:speciesID,
					(SELECT `key` FROM skin_color WHERE color = :skin_color)
				);"
			);
			$stmt->bindParam(':speciesID', $ID, PDO::PARAM_INT);
			$stmt->bindParam(':skin_color', $skin_color, PDO::PARAM_STR);
			$stmt->execute();
		}
		//insert all species' skin colors
		foreach (explode(',', $species->eye_colors) as $value) {
			$eye_color = trim($value);
			$stmt = $PDO->prepare(
				"INSERT INTO `create_opdracht`.`ct_species_eye_color` (
					`species`,
					`color`
				) VALUES (
					:speciesID,
					(SELECT `key` FROM eye_color WHERE color = :eye_color)
				);"
			);
			$stmt->bindParam(':speciesID', $ID, PDO::PARAM_INT);
			$stmt->bindParam(':eye_color', $eye_color, PDO::PARAM_STR);
			$stmt->execute();
		}
	}
	$PDO = null;
?>