package com.nibl.bot.database;

import java.sql.Connection;
import java.sql.SQLException;

import com.nibl.bot.Bot;
import com.nibl.bot.BotExtend;
import com.zaxxer.hikari.HikariConfig;
import com.zaxxer.hikari.HikariDataSource;

public class MySqlDatabase extends BotExtend implements Database {

    private final HikariDataSource dataSource;

    public MySqlDatabase(Bot myBot, String userName, String userPass, String server, String port, String dbName) {
        super(myBot);

        HikariConfig config = new HikariConfig();
        config.setJdbcUrl("jdbc:mysql://" + server + ":" + port + "/" + dbName
                + "?useUnicode=true&characterEncoding=UTF-8&connectionCollation=utf8mb4_unicode_ci&rewriteBatchedStatements=true&useSSL=false&allowPublicKeyRetrieval=true");
        config.setUsername(userName);
        config.setPassword(userPass);
        config.setMaximumPoolSize(12);
        config.setMinimumIdle(2);
        config.setConnectionTimeout(10_000);
        dataSource = new HikariDataSource(config);

        validateConnection();
    }

    /** Fail fast at startup if the database is unreachable. */
    private void validateConnection() {
        try (Connection ignored = dataSource.getConnection()) {
            _myBot.getLogger().info("Database connection established");
        } catch (SQLException e) {
            _myBot.getLogger().error("Cannot connect to database server", e);
            dataSource.close();
            System.exit(1);
        }
    }

    @Override
    public Connection getConnection() {
        try {
            return dataSource.getConnection();
        } catch (SQLException e) {
            throw new IllegalStateException("Failed to obtain a database connection", e);
        }
    }
}