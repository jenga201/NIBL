package com.nibl.bot.util;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.io.StringReader;
import java.util.Properties;
import java.util.regex.Matcher;

import com.nibl.bot.Bot;

public class BotProperties extends Properties {
	
	private static final long serialVersionUID = -5769945073010277585L;
	
	Bot _myBot;
	String configFile;

	public BotProperties(Bot myBot, String configFile) {
		_myBot = myBot;
		this.configFile = configFile;
		loadConfiguration();
		applyEnvOverrides();
	}

	/**
	 * Environment variables override file properties: property key "db_pass"
	 * is overridden by env var "DB_PASS" when present. Values are never logged.
	 */
	private void applyEnvOverrides() {
		for (String key : new java.util.ArrayList<>(stringPropertyNames())) {
			String env = System.getenv(key.toUpperCase());
			if (env != null) {
				setProperty(key, env);
				_myBot.getLogger().info("Property " + key + " overridden from environment variable " + key.toUpperCase());
			}
		}
	}
	
	public void loadConfiguration() {
		File file = new File(configFile);
		try {
			_myBot.getLogger().info("Reading config from : " + file.getAbsoluteFile());
			BufferedReader configFile = new BufferedReader( new FileReader( file ) );
			//BufferedReaderIterable configFile = new BufferedReaderIterable(_myBot, file);
    		String temp = "";
    		while ( (temp = configFile.readLine()) != null ) {
				load(new StringReader(temp.replaceFirst("!", "").replaceAll(Matcher.quoteReplacement("\\"), Matcher.quoteReplacement("---"))));
			}
    		configFile.close();
		} catch (FileNotFoundException fnfe) {
			_myBot.getLogger().error("File not found.  Expected at " + file.getAbsolutePath());
			System.exit(0);
		} catch (IOException e) {
			_myBot.getLogger().error("Reading config : " + file.getAbsolutePath());
		}
		_myBot.getLogger().info("Config done.");
	}

}