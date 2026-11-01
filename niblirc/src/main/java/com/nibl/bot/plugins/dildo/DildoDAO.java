package com.nibl.bot.plugins.dildo;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Random;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DataAccessObject;

public class DildoDAO extends DataAccessObject {

    private static String randomWordSQL = "SELECT `value` FROM words WHERE `type` = ? ORDER BY RAND() LIMIT 1";
    private static String randomAlternateWord = "SELECT `value` FROM words WHERE `type` = ? AND `value` != ? ORDER BY RAND() LIMIT 1";

    public DildoDAO(Bot bot) {
        super(bot);
    }

    @Override
    public void createTables() {
    }

    @Override
    public void dropTables() {
    }

    private String getRandomWord(Integer type) throws SQLException {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement(randomWordSQL)) {
            pstmt.setInt(1, type);
            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getString("value");
                } else {
                    throw new SQLException("Probably a db error");
                }
            }
        }
    }

    private String getRandomAlternateWord(String value, Integer type) throws SQLException {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement(randomAlternateWord)) {
            pstmt.setInt(1, type);
            pstmt.setString(2, value);
            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getString("value");
                } else {
                    throw new SQLException("Probably a db error");
                }
            }
        }
    }

    // 1=>Noun, 2=>Verb, 3=>Adjective, 4=>Adverb
    public String getRandomDildo(String caller, String receiver) {
        StringBuilder sb = new StringBuilder();
        try {
            String noun = getRandomWord(Dildo.NOUN);
            String verb = getRandomWord(Dildo.VERB);
            String adjective = getRandomWord(Dildo.ADJECTIVE);
            String adverb = getRandomWord(Dildo.ADVERB);

            Random rand = new Random();
            int randomNum;
            if (Math.random() >= 0.8) {
                randomNum = rand.nextInt((9999 - 1000) + 1) + 1000;
            } else {
                randomNum = rand.nextInt((100 - 1) + 1) + 1;
            }

            sb.append(caller);
            sb.append(" ");
            sb.append(adverb);
            sb.append(" ");
            sb.append(verb);
            sb.append("s");
            sb.append(" ");
            sb.append(receiver);
            sb.append(" ");
            sb.append("with");
            sb.append(" ");
            sb.append(randomNum);
            sb.append(" ");
            sb.append(adjective);
            sb.append(" ");
            sb.append("dildo");
            if (randomNum > 1) {
                sb.append("s");
            }
            sb.append(" ");
            sb.append("of");
            sb.append(" ");
            sb.append(getRandomAlternateWord(adjective, Dildo.ADJECTIVE));
            sb.append(" ");
            sb.append(noun);
        } catch (SQLException e) {
            _myBot.getLogger().error("Error when getting random dildo", e);
        }
        return sb.toString();
    }

}