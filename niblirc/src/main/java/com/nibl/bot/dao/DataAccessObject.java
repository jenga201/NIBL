package com.nibl.bot.dao;

import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.sql.ResultSet;
import java.sql.SQLException;

import com.nibl.bot.Bot;
import com.nibl.bot.BotExtend;

public abstract class DataAccessObject extends BotExtend {

    public DataAccessObject(Bot myBot) {
        super(myBot);
        // Schema is owned by Laravel migrations (nibl.co.uk/database/migrations);
        // DAOs no longer create or seed tables at startup.
    }

    /**
     * A pooled connection for the current operation. Callers must close it
     * (try-with-resources) so it returns to the pool.
     */
    public final Connection getConnection() {
        return _myBot.getDatabase().getConnection();
    }

    public final boolean tableExists(String table) {
        try (Connection connection = getConnection()) {
            DatabaseMetaData metaData = connection.getMetaData();
            try (ResultSet tables = metaData.getTables(null, null, table, new String[]{"TABLE"})) {
                if (tables.next()) {
                    return true;
                }
            }
            // some drivers report identifiers case-sensitively; retry upper case
            try (ResultSet tables = metaData.getTables(null, null, table.toUpperCase(), new String[]{"TABLE"})) {
                return tables.next();
            }
        } catch (SQLException e) {
            // table doesn't exist — eat the exception
            return false;
        }
    }

    public abstract void createTables();

    public abstract void dropTables();
}