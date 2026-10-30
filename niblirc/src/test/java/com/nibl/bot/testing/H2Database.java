package com.nibl.bot.testing;

import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

import org.h2.tools.RunScript;

import com.nibl.bot.database.Database;

/**
 * In-memory H2 {@link Database} in MySQL compatibility mode. The schema is
 * loaded once from /schema.sql on the test classpath; DB_CLOSE_DELAY=-1 keeps
 * the data alive for the lifetime of the JVM so the single cached connection
 * can be shared by every DAO (mirroring MySqlDatabase, which also hands out
 * one connection).
 */
public class H2Database implements Database {

    private final Connection connection;

    public H2Database(String dbName) throws SQLException {
        String url = "jdbc:h2:mem:" + dbName
                + ";MODE=MySQL;DATABASE_TO_LOWER=TRUE;CASE_INSENSITIVE_IDENTIFIERS=TRUE;DB_CLOSE_DELAY=-1";
        this.connection = DriverManager.getConnection(url, "sa", "");
        loadSchema();
    }

    private void loadSchema() throws SQLException {
        InputStream in = H2Database.class.getResourceAsStream("/schema.sql");
        if (in == null) {
            throw new SQLException("Test schema /schema.sql not found on the test classpath");
        }
        try (InputStreamReader reader = new InputStreamReader(in, StandardCharsets.UTF_8)) {
            RunScript.execute(connection, reader);
        } catch (IOException e) {
            throw new SQLException("Failed to read test schema /schema.sql", e);
        }
    }

    @Override
    public Connection getConnection() {
        return connection;
    }

    public void close() throws SQLException {
        connection.close();
    }
}
