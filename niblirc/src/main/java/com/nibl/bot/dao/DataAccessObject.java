package com.nibl.bot.dao;

import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.sql.ResultSet;
import java.sql.SQLException;

import com.nibl.bot.Bot;
import com.nibl.bot.BotExtend;

public abstract class DataAccessObject extends BotExtend {
	private Connection _connection;
	
	public DataAccessObject(Bot myBot) {
		super(myBot);
		_connection = _myBot.getDatabase().getConnection();
		createTables();
	}
	
	public final Connection getConnection()
	{
		return _connection;
	}
	
	public final boolean tableExists(String table) {
		// JDBC metadata instead of MySQL-specific `desc <table>` so the DAOs
		// also work against the in-memory H2 test database.
		try {
			DatabaseMetaData metaData = getConnection().getMetaData();
			try (ResultSet tables = metaData.getTables(null, null, table, new String[] { "TABLE" })) {
				if (tables.next()) {
					return true;
				}
			}
			// some drivers report identifiers case-sensitively; retry upper case
			try (ResultSet tables = metaData.getTables(null, null, table.toUpperCase(), new String[] { "TABLE" })) {
				return tables.next();
			}
		} catch (SQLException e) {
			// table doesn't exist eat exception
			return false;
		}
	}
	
	public abstract void createTables();
	public abstract void dropTables();
}
