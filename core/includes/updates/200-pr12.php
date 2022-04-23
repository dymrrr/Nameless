<?php
class Pre13 extends UpgradeScript {
    public function run(): void {
        // Default night mode to null instead of 0
        try {
            DB::getInstance()->createQuery('ALTER TABLE nl2_users MODIFY night_mode tinyint(1) DEFAULT NULL NULL');
        } catch (Exception $e) {
            // Continue
        }

        // Cookie policy
        try {
            if (!DB::getInstance()->selectQuery('SELECT `id` FROM nl2_privacy_terms WHERE `name` = ?', ['cookies'])->count()) {
                $this->_queries->create('privacy_terms', array(
                    'name' => 'cookies',
                    'value' => '<span style="font-size:18px"><strong>What are cookies?</strong></span><br />Cookies are small files which are stored on your device by a website, unique to your web browser. The web browser will send these files to the website each time it communicates with the website.<br />Cookies are used by this website for a variety of reasons which are outlined below.<br /><br /><strong>Necessary cookies</strong><br />Necessary cookies are required for this website to function. These are used by the website to maintain your session, allowing for you to submit any forms, log into the website amongst other essential behaviour. It is not possible to disable these within the website, however you can disable cookies altogether via your browser.<br /><br /><strong>Functional cookies</strong><br />Functional cookies allow for the website to work as you choose. For example, enabling the &quot;Remember Me&quot; option as you log in will create a functional cookie to automatically log you in on future visits.<br /><br /><strong>Analytical cookies</strong><br />Analytical cookies allow both this website, and any third party services used by this website, to collect non-personally identifiable data about the user. This allows us (the website staff) to continue to improve the user experience and understand how the website is used.<br /><br />Further information about cookies can be found online, including the <a rel="nofollow noopener" target="_blank" href="https://ico.org.uk/your-data-matters/online/cookies/">ICO&#39;s website</a> which contains useful links to further documentation about configuring your browser.<br /><br /><span style="font-size:18px"><strong>Configuring cookie use</strong></span><br />By default, only necessary cookies are used by this website. However, some website functionality may be unavailable until the use of cookies has been opted into.<br />You can opt into, or continue to disallow, the use of cookies using the cookie notice popup on this website. If you would like to update your preference, the cookie notice popup can be re-enabled by clicking the button below.'
                ));
            }
        } catch (Exception $e) {
            // Continue
        }

        // delete old "version" row
        try {
            DB::getInstance()->createQuery('DELETE FROM nl2_settings WHERE `name` = ?', ['version']);
        } catch (Exception $e) {
            // Continue
        }

        try {
            DB::getInstance()->createQuery("CREATE TABLE `nl2_oauth` (
                                          `provider` varchar(256) NOT NULL,
                                          `enabled` tinyint(1) NOT NULL DEFAULT '0',
                                          `client_id` varchar(256) DEFAULT NULL,
                                          `client_secret` varchar(256) DEFAULT NULL,
                                          PRIMARY KEY (`provider`),
                                          UNIQUE KEY `id` (`provider`)
                                        ) ENGINE=$this->_db_engine DEFAULT CHARSET=$this->_db_charset");
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        try {
            DB::getInstance()->createQuery("CREATE TABLE `nl2_oauth_users` (
                                          `user_id` int NOT NULL,
                                          `provider` varchar(256) NOT NULL,
                                          `provider_id` varchar(256) NOT NULL,
                                          PRIMARY KEY (`user_id`,`provider`,`provider_id`),
                                          UNIQUE KEY `user_id` (`user_id`,`provider`,`provider_id`)
                                        ) ENGINE=$this->_db_engine DEFAULT CHARSET=$this->_db_charset");
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        // User integrations
        try {
            DB::getInstance()->createTable('integrations', " `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(32) NOT NULL, `enabled` tinyint(1) NOT NULL DEFAULT '1', `can_unlink` tinyint(1) NOT NULL DEFAULT '1', `required` tinyint(1) NOT NULL DEFAULT '0', `order` int(11) NOT NULL DEFAULT '0', PRIMARY KEY (`id`)", "ENGINE=$this->_db_engine DEFAULT CHARSET=$this->_db_charset");
            DB::getInstance()->createTable('users_integrations', " `id` int(11) NOT NULL AUTO_INCREMENT, `integration_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `identifier` varchar(64) DEFAULT NULL, `username` varchar(32) DEFAULT NULL, `verified` tinyint(1) NOT NULL DEFAULT '0', `date` int(11) NOT NULL, `code` varchar(64) DEFAULT NULL, `show_publicly` tinyint(1) NOT NULL DEFAULT '1', `last_sync` int(11) NOT NULL DEFAULT '0', PRIMARY KEY (`id`)", "ENGINE=$this->_db_engine DEFAULT CHARSET=$this->_db_charset");

            $this->_queries->create('integrations', [
                'name' => 'Minecraft',
                'enabled' => 1,
                'can_unlink' => 0,
                'required' => 0
            ]);

            $this->_queries->create('integrations', [
                'name' => 'Discord',
                'enabled' => 1,
                'can_unlink' => 1,
                'required' => 0
            ]);
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        try {
            DB::getInstance()->createQuery('ALTER TABLE `nl2_users_integrations` ADD INDEX `nl2_users_integrations_idx_integration_id` (`integration_id`)');
            DB::getInstance()->createQuery('ALTER TABLE `nl2_users_integrations` ADD INDEX `nl2_users_integrations_idx_user_id` (`user_id`)');
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        // Convert users integrations
        try {
            $users = DB::getInstance()->selectQuery('SELECT id, username, uuid, discord_id, discord_username, joined FROM nl2_users')->results();
            $query = 'INSERT INTO nl2_users_integrations (integration_id, user_id, identifier, username, verified, date) VALUES ';
            foreach ($users as $item) {
                $inserts = [];

                if (!empty($item->uuid) && $item->uuid != 'none') {
                    $inserts[] = '(1,' . $item->id . ',\'' . $item->uuid . '\',\'' . $item->username . '\',1,' . $item->joined . '),';
                }

                if ($item->discord_id != null && $item->discord_username != null && $item->discord_id != 010) {
                    $inserts[] = '(2,' . $item->id . ',\'' . $item->discord_id . '\',\'' . $item->discord_username . '\',1,' . $item->joined . '),';
                }

                $query .= implode('', $inserts);
            }
            DB::getInstance()->createQuery(rtrim($query, ','));

            // Only delete after successful conversion
            DB::getInstance()->createQuery('ALTER TABLE `nl2_users` DROP COLUMN `uuid`;');
            DB::getInstance()->createQuery('ALTER TABLE `nl2_users` DROP COLUMN `discord_id`;');
            DB::getInstance()->createQuery('ALTER TABLE `nl2_users` DROP COLUMN `discord_username`;');
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        // Add bedrock to nl2_mc_servers table
        try {
            DB::getInstance()->createQuery('ALTER TABLE `nl2_mc_servers` ADD `bedrock` tinyint(1) NOT NULL DEFAULT \'0\'');
        } catch (Exception $e) {
            echo $e->getMessage() . '<br />';
        }

        // Increase length of name column
        try {
            DB::getInstance()->createQuery('ALTER TABLE nl2_mc_servers MODIFY `name` VARCHAR(128) NOT NULL');
        } catch (Exception $e) {
            // Continue
        }

        // add unique constraint to modules table
        try {
            DB::getInstance()->createQuery('ALTER TABLE nl2_modules ADD UNIQUE (`name`)');
        } catch (Exception $e) {
            // Continue
        }

        // Increase length of reset_code column
        try {
            DB::getInstance()->createQuery('ALTER TABLE nl2_users MODIFY `reset_code` VARCHAR(64) NOT NULL');
        } catch (Exception $e) {
            // Continue
        }

        // delete language cache since it will contain the old language names and not the short codes
        $this->_cache->setCache('languagecache');
        $this->_cache->eraseAll();

        // add short code column to languages table
        try {
            DB::getInstance()->createQuery('ALTER TABLE nl2_languages ADD `short_code` VARCHAR(64) NOT NULL');
        } catch (Exception $e) {
            // Continue
        }

        $languages = DB::getInstance()->selectQuery('SELECT * FROM nl2_languages')->results();
        $converted_languages = [];
        foreach (Language::LANGUAGES as $short_code => $meta) {
            $key = array_search(str_replace(' ', '', $meta['name']), array_column($languages, 'name'));

            if ($key !== false) {
                $this->_queries->update('languages', $languages[$key]->id, [
                    'name' => $meta['name'],
                    'short_code' => $short_code
                ]);

                $converted_languages[] = $languages[$key]->id;
            } else {
                $this->_queries->create('languages', [
                    'name' => $meta['name'],
                    'short_code' => $short_code
                ]);

                $converted_languages[] = $this->_queries->getLastId();
            }
        }

        try {
            DB::getInstance()->createQuery('DELETE FROM nl2_languages WHERE `short_code` IS NULL');

            $default_language = DB::getInstance()->selectQuery('SELECT id FROM nl2_languages WHERE `is_default` = 1');

            if (!$default_language->count()) {
                // Default to 1 (EnglishUK)
                $default_language = 1;
                DB::getInstance()->createQuery('UPDATE nl2_languages SET `is_default` = 1 WHERE `id` = 1');
            } else {
                $default_language = $default_language->first()->id;
            }

            DB::getInstance()->createQuery('UPDATE nl2_users SET `language_id` = ? WHERE `language_id` NOT IN (' . implode(', ', $converted_languages) . ')', [$default_language]);
        } catch (Exception $e) {
            // Continue
        }

        // Update version number
        /*$version_number_id = $queries->getWhere('settings', array('name', '=', 'nameless_version'));

        if (count($version_number_id)) {
            $version_number_id = $version_number_id[0]->id;
            $queries->update('settings', $version_number_id, array(
                'value' => '2.0.0-pr13'
            ));
        } else {
            $queries->create('settings', array(
                'name' => 'nameless_version',
                'value' => '2.0.0-pr13'
            ));
        }*/

        $version_update_id = $this->_queries->getWhere('settings', array('name', '=', 'version_update'));
        $version_update_id = $version_update_id[0]->id;

        $this->_queries->update('settings', $version_update_id, array(
            'value' => 'false'
        ));
    }
}

return new Pre13();
