package com.nibl.bot.testing;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.Properties;
import java.util.UUID;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DAOFactory;
import com.nibl.bot.database.Database;

/**
 * A {@link Bot} that never touches IRC, MySQL or the config file. It runs
 * against a per-instance in-memory H2 database seeded from
 * src/test/resources/schema.sql, and builds the real {@link DAOFactory} on
 * top of it so DAOs behave exactly as in production.
 *
 * Each instance gets a unique database name, so test classes are isolated
 * from each other.
 */
public class TestBot extends Bot {

    private final H2Database database;
    private final DAOFactory daoFactory;
    private final Properties properties = new Properties();

    public TestBot() throws SQLException {
        this("niblirc_test_" + UUID.randomUUID().toString().replace("-", ""));
    }

    public TestBot(String dbName) throws SQLException {
        super();
        this.database = new H2Database(dbName);
        this.daoFactory = new DAOFactory(this);
    }

    @Override
    public Database getDatabase() {
        return database;
    }

    @Override
    public DAOFactory getDAOFactory() {
        return daoFactory;
    }

    @Override
    public String getProperty(String key) {
        return properties.getProperty(key);
    }

    @Override
    public void setProperty(String key, String value) {
        properties.setProperty(key, value);
    }

    public Connection getConnection() {
        return database.getConnection();
    }

    /** Inserts a fake bot row into updatepacklist_bots and returns its generated id. */
    public int insertBot(String name, String url, String type, String owner, int statusId, int parserId)
            throws SQLException {
        String sql = "INSERT INTO updatepacklist_bots "
                + "(name, url, type, owner, status_id, last_seen, last_processed, informative, batchenable, external, parser_id) "
                + "VALUES (?, ?, ?, ?, ?, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 0, 1, 0, ?)";
        PreparedStatement pstmt = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS);
        pstmt.setString(1, name);
        pstmt.setString(2, url);
        pstmt.setString(3, type);
        pstmt.setString(4, owner);
        pstmt.setInt(5, statusId);
        pstmt.setInt(6, parserId);
        pstmt.executeUpdate();
        ResultSet keys = pstmt.getGeneratedKeys();
        if (!keys.next()) {
            pstmt.close();
            throw new SQLException("No generated key returned for bot " + name);
        }
        int id = keys.getInt(1);
        pstmt.close();
        return id;
    }

    /** Inserts a fake pack row into updatepacklist_packs. */
    public void insertPack(int botId, int number, String name, String size, long sizeKBits, int episodeNumber,
            String lastModified) throws SQLException {
        String sql = "INSERT INTO updatepacklist_packs "
                + "(bot_id, number, name, size, sizekbits, episode_number, last_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        PreparedStatement pstmt = getConnection().prepareStatement(sql);
        pstmt.setInt(1, botId);
        pstmt.setInt(2, number);
        pstmt.setString(3, name);
        pstmt.setString(4, size);
        pstmt.setLong(5, sizeKBits);
        pstmt.setInt(6, episodeNumber);
        pstmt.setString(7, lastModified);
        pstmt.executeUpdate();
        pstmt.close();
    }
}
