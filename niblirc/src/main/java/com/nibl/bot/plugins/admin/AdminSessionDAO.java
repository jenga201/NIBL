package com.nibl.bot.plugins.admin;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.LinkedList;

import org.pircbotx.User;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DataAccessObject;

public class AdminSessionDAO extends DataAccessObject {

    public AdminSessionDAO(Bot myBot) {
        super(myBot);
        updateCurrentBotUsers();
    }

    @Override
    public void createTables() {
        if (!tableExists("bot_admins")) {
            try (Connection connection = getConnection();
                 Statement statement = connection.createStatement()) {
                statement.execute("CREATE TABLE IF NOT EXISTS `bot_admins` (`user_name` varchar(30) NOT NULL,`password` char(32) NOT NULL,`access` smallint unsigned NOT NULL, PRIMARY KEY  (`user_name`)) ENGINE=InnoDB;");
            } catch (SQLException e) {
                _myBot.getLogger().error("createTables", e);
            }
            _myBot.getLogger().info("Tables Created for Admin Users");
        }
    }

    @Override
    public void dropTables() {
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            statement.execute("DROP TABLE IF EXISTS bot_admins;");
        } catch (SQLException e) {
            _myBot.getLogger().error("dropTables", e);
        }
    }

    public String addAdmin(String info) {
        String[] stuff = info.split(" ");
        String output = "";
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            StringBuilder sb = new StringBuilder();
            sb.append("insert into bot_admins (user_name,password,access) values ('");
            sb.append(stuff[1].toLowerCase() + "'," + "MD5('" + stuff[2] + "')," + stuff[3] + ")");
            sb.append(" on duplicate key update ******'");
            sb.append(stuff[2] + "'), access = " + stuff[3] + ";");
            statement.executeUpdate(sb.toString());
            output = "Added " + stuff[1] + " with access level " + stuff[3];
            updateCurrentBotUsers();
        } catch (SQLException e) {
            output = "invalid syntax";
        }
        return output;
    }

    public String delAdmin(String user) {
        String output = "";
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            StringBuilder sb = new StringBuilder();
            sb.append("DELETE FROM ooinuza.bot_admins WHERE user_name = '" + user + "' AND user_name != 'jenga' AND user_name != 'sirus' LIMIT 1");
            int affectedRows = statement.executeUpdate(sb.toString());
            if (affectedRows == 1) {
                output = "Deleted " + user;
            } else if (affectedRows > 1) {
                output = "Error: affected rows " + affectedRows;
            } else {
                output = "Unknown user: " + user;
            }
            updateCurrentBotUsers();
        } catch (SQLException e) {
            output = "invalid syntax";
        }
        return output;
    }

    public String setAdminLogLevel(String loglevel, String username) {
        try {
            int logLevel = Integer.parseInt(loglevel);
            if (!(logLevel == 0 || logLevel == 1 || logLevel == 2 || logLevel == 3)) {
                return "Log level " + loglevel + " is not valid.  Must be either 0, 1, 2, or 3!";
            }
        } catch (Exception e) {
            return "Log level must be either 0, 1, 2, or 3!";
        }

        String output = "";
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            StringBuilder sb = new StringBuilder();
            sb.append("UPDATE ooinuza.bot_admins SET loglevel = '" + loglevel + "' WHERE user_name = '" + username + "' LIMIT 1");
            int affectedRows = statement.executeUpdate(sb.toString());
            if (affectedRows == 1) {
                output = "Updated log level to " + loglevel;
            } else if (affectedRows > 1) {
                output = "Error: affected rows " + affectedRows;
            } else {
                output = "Unknown user: " + username;
            }
            updateCurrentBotUsers();
        } catch (SQLException e) {
            output = "invalid syntax";
        }
        return output;
    }

    public String changePass(String pw, String username) {
        String output = "";
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            StringBuilder sb = new StringBuilder();
            sb.append("update bot_admins set ******'" + pw + "')");
            sb.append(" where user_name='" + username.toLowerCase() + "';");
            statement.executeUpdate(sb.toString());
            output = "Updated " + username + " with new password";
            updateCurrentBotUsers();
        } catch (SQLException e) {
            output = "invalid syntax";
        }
        return output;
    }

    public void updateCurrentBotUsers() {
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            ResultSet rs = statement.executeQuery("select * from bot_admins;");
            LinkedList<BotUser> admins = new LinkedList<BotUser>();
            while (rs.next()) {
                BotUser admin = new BotUser();
                admin.setUserName(rs.getString("user_name"));
                admin.setPassword(rs.getString("password"));
                admin.setAccessLevel(rs.getInt("access"));
                admin.setLogLevel(rs.getInt("loglevel"));
                admins.add(admin);
            }
            _myBot.setBotAdmins(admins);
        } catch (SQLException e) {
            _myBot.getLogger().error("Error getting current admins", e);
        }
    }

    public Integer getAccessLevel(String user, String password) {
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            ResultSet rs = statement.executeQuery("select * from bot_admins where user_name = '" + user + "' AND password = MD5('" + password + "');");
            if (rs.next()) {
                return rs.getInt(3);
            }
        } catch (SQLException e) {
            _myBot.getLogger().error("getAccessLevel", e);
        }
        return -1;
    }

    public Integer getAccessLevel(User user) {
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            ResultSet rs = statement.executeQuery("select * from bot_admins where user_name = '" + user.getNick().toLowerCase() + "'");
            if (rs.next()) {
                return rs.getInt(3);
            }
        } catch (SQLException e) {
            _myBot.getLogger().error("getAccessLevel", e);
        }
        return -1;
    }

    public Boolean authorizeUser(User user, String password) {
        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {
            ResultSet rs = statement.executeQuery("select * from bot_admins where user_name = '" + user.getNick().toLowerCase() + "' AND password = MD5('" + password + "');");
            return (rs.next());
        } catch (SQLException e) {
            _myBot.getLogger().error("authorizeUser", e);
        }
        return false;
    }

}