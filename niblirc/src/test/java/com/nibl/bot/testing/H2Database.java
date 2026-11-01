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
 * loaded once from /schema.sql on the test classpath. {@link #getConnection()}
 * hands out a fresh connection each call (the DAOs close them), while the
 * keeper connection holds the in-memory database open for the test JVM.
 */
public class H2Database implements Database {

    private final String url;
    private final Connection keeper;

    public H2Database(String dbName) throws SQLException {
        this.url = "jdbc:h2:mem:" + dbName
                + ";MODE=MySQL;DATABASE_TO_LOWER=TRUE;CASE_INSENSITIVE_IDENTIFIERS=TRUE;DB_CLOSE_DELAY=-1";
        this.keeper = DriverManager.getConnection(url, "sa", "");
        loadSchema(keeper);
    }

    private void loadSchema(Connection connection) throws SQLException {
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
        try {
            return DriverManager.getConnection(url, "sa", "");
        } catch (SQLException e) {
            throw new IllegalStateException("Failed to obtain an H2 test connection", e);
        }
    }
}