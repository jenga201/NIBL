package com.nibl.bot.plugins.checknameregistered;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;

import org.pircbotx.User;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DataAccessObject;

public class CheckNameRegisteredDAO extends DataAccessObject {

	public CheckNameRegisteredDAO(Bot myBot) {
		super(myBot);
	}

	public Integer getUserAccessLevel(User user) {
		try {
			if (user.isVerified()) {
				try (Connection connection = getConnection();
						Statement statement = connection.createStatement()) {
					ResultSet rs = statement.executeQuery("select access from bot_admins where user_name = '" + user.getNick().toLowerCase() + "';");
					if (rs.next()) {
						return rs.getInt(1);
					}
				}
			}
		} catch (SQLException e) {
			_myBot.getLogger().error("getUserAccessLevel", e);
		}
		return -1;
	}

	@Override
	public void createTables() {
	}

	@Override
	public void dropTables() {
	}

}