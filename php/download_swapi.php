<?php
    include_once "db_connect.php";

    /**
     * Downloads all objects of a type available on swapi.co
     *
     * @param string $type The type to download can be {"films", "people", "planets", "species", "starships", "vehicles"}
     * @return array An array containing all the objects found on swapi, parsed using JSON
     */
    function downloadSwapiList(string $type) : array {
        // Download initial page
        $content = json_decode(file_get_contents("http://swapi.co/api/" . $type . "?format=json"));
        $count   = 0;
        $result  = array_fill(0, $content->count, null); // Instantiate array of content size
        while (true) {
            foreach ($content->results as $content_result) // Iterate over every result from the content
                $result[$count++] = $content_result; // Add the content to the result

            if (!isset($content->next))
                return $result; // Return the results if there is no next page
            $content = json_decode(file_get_contents($content->next)); // Otherwise, download next page
        }
    }

    // I know parameterised queries arent exactly necessary, but I still use them as they are a good habit

    $PDO = DB_getConnection(); // Connect to database

    // Clear data
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

    // Download all people and iterate over them
    foreach (downloadSwapiList("people") as $index => $person) {
        $ID        = $index + 1;
        // Removing BBY from birthYear and parsing to int
        $birthYear = null;
        if ($person->birth_year != "unknown")
            $birthYear = intval(substr($person->birth_year, 0, strlen($person->birth_year) - 3));
        $mass      = $person->mass == 0 ? null : $person->mass;
        $height    = $person->height == 0 ? null : $person->height;

        // Insert the person
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
        $stmt->bindParam(":birth_year", $birthYear, PDO::PARAM_INT);
        $stmt->bindParam(":gender", $person->gender, PDO::PARAM_STR);
        
        $stmt->execute();
        
        // Insert all people's hair colors
        foreach (explode(',', $person->hair_color) as $value) {
            $hairColor = trim($value);

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
            $stmt->bindParam(':hair_color', $hairColor, PDO::PARAM_STR);
            $stmt->execute();
        }
        // Insert all people's skin colors
        foreach (explode(',', $person->skin_color) as $value) {
            $skinColor = trim($value);

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
            $stmt->bindParam(':skin_color', $skinColor, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    foreach (downloadSwapiList("species") as $index => $species) {
        $ID = $index + 1;

        $averageHeight   = $species->average_height == 0 ? null : $species->average_height;
        $averageLifespan = $species->average_lifespan == 0 ? null : $species->average_lifespan;

        // Insert the species
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
        $stmt->bindParam(":average_height", $averageHeight, PDO::PARAM_INT);
        $stmt->bindParam(":average_lifespan", $averageLifespan, PDO::PARAM_INT);
        $stmt->bindParam(":language", $species->language, PDO::PARAM_STR);
        
        $stmt->execute();
        
        // Insert all species' hair colors
        foreach (explode(',', $species->hair_colors) as $value) {
            $hairColor = trim($value);

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
            $stmt->bindParam(':hair_color', $hairColor, PDO::PARAM_STR);
            $stmt->execute();
        }
        // Insert all species' skin colors
        foreach (explode(',', $species->skin_colors) as $value) {
            $skinColor = trim($value);

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
            $stmt->bindParam(':skin_color', $skinColor, PDO::PARAM_STR);
            $stmt->execute();
        }
        // Insert all species' skin colors
        foreach (explode(',', $species->eye_colors) as $value) {
            $eyeColor = trim($value);
            $stmt     = $PDO->prepare(
                "INSERT INTO `create_opdracht`.`ct_species_eye_color` (
                    `species`,
                    `color`
                ) VALUES (
                    :speciesID,
                    (SELECT `key` FROM eye_color WHERE color = :eye_color)
                );"
            );
            $stmt->bindParam(':speciesID', $ID, PDO::PARAM_INT);
            $stmt->bindParam(':eye_color', $eyeColor, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    $PDO = null;
?>