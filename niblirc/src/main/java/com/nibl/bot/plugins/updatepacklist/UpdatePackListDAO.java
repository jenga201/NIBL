package com.nibl.bot.plugins.updatepacklist;

import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.sql.Timestamp;
import java.util.HashMap;
import java.util.HashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.Objects;

import org.pircbotx.Colors;
import org.pircbotx.dcc.SendChat;
import org.pircbotx.hooks.events.MessageEvent;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DataAccessObject;
import com.nibl.bot.plugins.latestpacks.LatestPacksDAO;

public class UpdatePackListDAO extends DataAccessObject {

    private final String _STATUS_TABLE = "updatepacklist_status";
    private final String _BOTS_TABLE = "updatepacklist_bots";
    private final String _PACKS_TABLE = "updatepacklist_packs";
    private UpdatePackList _updatePackList;

    public UpdatePackListDAO(Bot myBot) {
        super(myBot);
    }

    public void registerUpdatePackList(UpdatePackList updatePackList) {
        _updatePackList = updatePackList;
    }

    @Override
    public void createTables() {
        if (tablesDoesNotExist()) {
            try (Connection connection = getConnection();
                 Statement statement = connection.createStatement()) {
                statement.execute("CREATE TABLE IF NOT EXISTS `" + _STATUS_TABLE + "` (`id` INT(2) NOT NULL,`description` VARCHAR(20) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB;");
                statement.execute("TRUNCATE `" + _STATUS_TABLE + "`;");
                statement.execute("INSERT INTO `" + _STATUS_TABLE + "` (`id`,`description`) VALUES ('1','ONLINE'), ('0','OFFLINE');");
                statement.execute("CREATE TABLE IF NOT EXISTS `" + _BOTS_TABLE + "` (`id` INT(12) NOT NULL auto_increment,`name` VARCHAR(40) NOT NULL,`url` VARCHAR(200) NOT NULL,`type` VARCHAR(10) NOT NULL, `owner` VARCHAR(255), `status_id` INT(2) NOT NULL,`last_seen` DATETIME NOT NULL,`last_processed` DATETIME NOT NULL,`informative` tinyint(4) NOT NULL,PRIMARY KEY (`id`),UNIQUE (`name`),FOREIGN KEY (`status_id`) REFERENCES `updatepacklist_status` (`id`)) ENGINE=InnoDB;");
                statement.execute("CREATE TABLE IF NOT EXISTS `" + _PACKS_TABLE + "` (`id` INT(12) NOT NULL auto_increment,`bot_id` INT(12) NOT NULL,`number` INT(12) NOT NULL,`name` VARCHAR(300) NOT NULL,`size` VARCHAR(8) NOT NULL,`last_modified` DATETIME NOT NULL,PRIMARY KEY (`id`),UNIQUE (`bot_id`,`number`),FOREIGN KEY (`bot_id`) REFERENCES `updatepacklist_bots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE) ENGINE=InnoDB;");
                _myBot.getLogger().info("created update pack list tables");
            } catch (SQLException e) {
                _myBot.getLogger().error("createTables", e);
            }
        }
    }

    private boolean tablesDoesNotExist() {
        return !tableExists(_STATUS_TABLE) || !tableExists(_BOTS_TABLE) || !tableExists(_PACKS_TABLE);
    }

    @Override
    public void dropTables() {
        if (tableExists("bot_info")) {
            try (Connection connection = getConnection();
                 Statement statement = connection.createStatement()) {
                statement.execute("DROP TABLE `" + _PACKS_TABLE + "`;");
                statement.execute("DROP TABLE `" + _BOTS_TABLE + "`;");
                statement.execute("DROP TABLE `" + _STATUS_TABLE + "`;");
                _myBot.getLogger().info("deleted update pack list tables");
            } catch (SQLException e) {
                _myBot.getLogger().error("dropTables", e);
            }
        }
    }

    public void getBotsFromDatabaseCreateBots() {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            ResultSet rs = getResultSetOfBotTable(stmt);
            while (rs.next()) {
                AbstractDistroBot distroBot = buildDistroBot(rs);
                _updatePackList.getBotsMap().put(distroBot.getId(), distroBot);
                _updatePackList.getReverseBotsMap().put(distroBot.getName().toLowerCase(), distroBot.getId());
                distroBot.setListing(getBotPackListFromDatabase(distroBot));
            }
            rs.close();
        } catch (SQLException e) {
            _myBot.getLogger().error("getBotsFromDatabaseCreateBots", e);
        }
    }

    public AbstractDistroBot getBotFromDatabase(String botName) {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            String query = "select * from " + _BOTS_TABLE + " where LOWER(`name`) = LOWER('" + botName + "')";
            ResultSet rs = stmt.executeQuery(query);
            if (rs.next()) {
                return buildDistroBot(rs);
            }
        } catch (SQLException e) {
            _myBot.getLogger().error("Error when getting bot " + this.getClass().getName(), e);
        }
        return null;
    }

    private ResultSet getResultSetOfBotTable(Statement stmt) throws SQLException {
        String query = "select * from " + _BOTS_TABLE + " order by `id`;";
        return stmt.executeQuery(query);
    }

    public void setBotStatus(AbstractDistroBot bot) throws SQLException {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            String query = "update " + _BOTS_TABLE + " set status_id = " + bot.getStatusId() + " where id = " + bot.getId();
            stmt.executeUpdate(query);
        }
    }

    public void setBotLastSeen(AbstractDistroBot bot) throws SQLException {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            String query = "update " + _BOTS_TABLE + " set last_seen = NOW() where id = " + bot.getId();
            stmt.executeUpdate(query);
        }
    }

    public void setBotParser(AbstractDistroBot bot) throws SQLException {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            String query = "update " + _BOTS_TABLE + " set parser_id = " + bot.getParserId() + " where id = " + bot.getId();
            stmt.executeUpdate(query);
        }
    }

    public AbstractDistroBot buildDistroBot(ResultSet rs) throws SQLException {
        int id = rs.getInt("id");
        String name = rs.getString("name");
        String url = rs.getString("url");
        String type = rs.getString("type");
        int statusId = rs.getInt("status_id");
        Timestamp lastSeen = rs.getTimestamp("last_seen");
        Timestamp lastProcessed = rs.getTimestamp("last_processed");
        int informative = rs.getInt("informative");
        int external = rs.getInt("external");
        String owner = rs.getString("owner");
        int parserId = rs.getInt("parser_id");
        LinkedList<Pack> listing = new LinkedList<Pack>();
        return DistroBotFactory.create(_myBot, id, name, url, type, statusId, lastSeen, lastProcessed, listing, informative, external, owner, parserId);
    }

    public AbstractDistroBot buildDistroBot(int id, String name, String url, String type, int statusId, Timestamp lastSeen, Timestamp lastProcessed, int informative, int external, String owner, int parserId) throws SQLException {
        LinkedList<Pack> listing = new LinkedList<Pack>();
        return DistroBotFactory.create(_myBot, id, name, url, type, statusId, lastSeen, lastProcessed, listing, informative, external, owner, parserId);
    }

    public List<Pack> getBotPackListFromDatabase(AbstractDistroBot bot) {
        LinkedList<Pack> listing = new LinkedList<Pack>();
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            ResultSet rs = stmt.executeQuery("SELECT * FROM updatepacklist_packs WHERE bot_id = " + bot.getId());
            while (rs.next()) {
                Pack pack = new Pack(rs.getInt("bot_id"), rs.getInt("number"), bot.getName(), rs.getString("name"), rs.getString("size"), rs.getLong("sizekbits"), rs.getInt("episode_number"), rs.getTimestamp("last_modified"));
                pack.setId(rs.getInt("id"));
                listing.add(pack);
            }
            rs.close();
        } catch (SQLException e) {
            _myBot.getLogger().error("getBotPackListFromDatabase: " + e.getMessage());
        }
        return listing;
    }

    public List<AbstractDistroBot> getBotsInfo() {
        List<AbstractDistroBot> result = new LinkedList<AbstractDistroBot>();
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            ResultSet rs = getResultSetOfBotTable(stmt);
            while (rs.next()) {
                AbstractDistroBot distroBot = buildDistroBot(rs);
                result.add(distroBot);
            }
            rs.close();
        } catch (SQLException e) {
            _myBot.getLogger().error("getBotsInfo", e);
        }
        return result;
    }

    public HashSet<String> getNoticeBotNames() {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            String query = "select name from " + _BOTS_TABLE + " where type='NOTICE';";
            ResultSet rs = stmt.executeQuery(query);
            HashSet<String> result = new HashSet<String>();
            while (rs.next()) {
                result.add(rs.getString(1));
            }
            rs.close();
            return result;
        } catch (SQLException e) {
            _myBot.getLogger().error("Error when getNoticeBots within " + this.getClass().getName(), e);
        }
        return new HashSet<String>();
    }

    /**
     * Reconcile the bot's stored packs against a freshly parsed listing, matched
     * by pack name (the stable file identity) rather than the XDCC number. A
     * brand-new name is inserted, a vanished name is deleted, and any pack whose
     * number, name, size, sizekbits or episode number changed is rewritten in
     * full (number included), so a bot that renumbers/reorders its list is
     * reflected exactly instead of drifting off by one.
     * <p>
     * Renumbering can violate the {@code (bot_id, number)} unique index when two
     * rows swap or shift, so rows whose number is changing are first parked on
     * the unique negative sentinel {@code number = -id}, then moved to their
     * final number once the destination is free. The whole reconcile runs in one
     * transaction and flushes write batches in chunks so a 220k-pack bot cannot
     * exhaust the heap.
     */
    public void updateListing(AbstractDistroBot bot, List<Pack> oldListing, List<Pack> newListing) {
        final int MAX_BATCH = 5000;
        final String updateSql = "UPDATE " + _PACKS_TABLE + " SET number=?, name=?, size=?, sizekbits=?, episode_number=?, last_modified=? WHERE id=?";
        final String renumberSql = "UPDATE " + _PACKS_TABLE + " SET number=? WHERE id=?";
        final String insertSql = "INSERT INTO " + _PACKS_TABLE + " (bot_id, number, name, size, sizekbits, episode_number, last_modified) VALUES (?,?,?,?,?,?,?)";
        final String deleteSql = "DELETE FROM " + _PACKS_TABLE + " WHERE id=?";
        final String botUpdateSql = "UPDATE " + _BOTS_TABLE + " SET last_processed = NOW() WHERE id=?";

        try {
            Map<String, Pack> oldByName = new HashMap<String, Pack>();
            for (Pack old : oldListing) {
                oldByName.put(nameOf(old), old);
            }

            HashSet<String> newNames = new HashSet<String>();
            for (Pack pack : newListing) {
                newNames.add(nameOf(pack));
            }

            try (Connection connection = getConnection();
                 PreparedStatement update = connection.prepareStatement(updateSql);
                 PreparedStatement renumber = connection.prepareStatement(renumberSql);
                 PreparedStatement insert = connection.prepareStatement(insertSql);
                 PreparedStatement delete = connection.prepareStatement(deleteSql);
                 PreparedStatement botUpdate = connection.prepareStatement(botUpdateSql)) {

                connection.setAutoCommit(false);
                try {
                    int pending = 0;

                    // 1) Delete packs that vanished from the listing.
                    for (Pack old : oldListing) {
                        if (!newNames.contains(nameOf(old))) {
                            delete.setInt(1, old.getId());
                            delete.addBatch();
                            if (++pending >= MAX_BATCH) {
                                delete.executeBatch();
                                pending = 0;
                            }
                        }
                    }
                    delete.executeBatch();
                    pending = 0;

                    // 2) Park rows whose number is changing on a unique negative
                    //    sentinel so the (bot_id, number) index cannot collide.
                    for (Pack pack : newListing) {
                        Pack old = oldByName.get(nameOf(pack));
                        if (old != null && old.getNumber() != pack.getNumber()) {
                            renumber.setInt(1, -old.getId());
                            renumber.setInt(2, old.getId());
                            renumber.addBatch();
                            if (++pending >= MAX_BATCH) {
                                renumber.executeBatch();
                                pending = 0;
                            }
                        }
                    }
                    renumber.executeBatch();
                    pending = 0;

                    // 3) Insert brand-new packs.
                    for (Pack pack : newListing) {
                        if (oldByName.containsKey(nameOf(pack))) {
                            continue;
                        }
                        setInsertParams(insert, pack);
                        insert.addBatch();
                        if (++pending >= MAX_BATCH) {
                            insert.executeBatch();
                            pending = 0;
                        }
                    }
                    insert.executeBatch();

                    // 4) Rewrite matched packs whose row changed (number included).
                    pending = 0;
                    for (Pack pack : newListing) {
                        Pack old = oldByName.get(nameOf(pack));
                        if (old != null && isChanged(old, pack)) {
                            setUpdateParams(update, pack, old.getId());
                            update.addBatch();
                            if (++pending >= MAX_BATCH) {
                                update.executeBatch();
                                pending = 0;
                            }
                        }
                    }
                    update.executeBatch();

                    // Bump last_processed in the same transaction so cache
                    // invalidation and the Meilisearch reader see an atomic change.
                    botUpdate.setInt(1, bot.getId());
                    botUpdate.executeUpdate();

                    connection.commit();
                } catch (SQLException e) {
                    connection.rollback();
                    throw e;
                }
            }
        } catch (SQLException e) {
            _myBot.getLogger().error("UpdatePackListDAO BotName: " + bot.getName(), e);
        }
    }

    private static String nameOf(Pack pack) {
        String name = pack.getName();
        return name == null ? "" : name;
    }

    private static void setInsertParams(PreparedStatement insert, Pack pack) throws SQLException {
        insert.setInt(1, pack.getBotId());
        insert.setInt(2, pack.getNumber());
        insert.setString(3, pack.getName());
        insert.setString(4, pack.getSize());
        insert.setLong(5, pack.getSizeKBits());
        insert.setInt(6, pack.getEpisodeNumber());
        insert.setTimestamp(7, pack.getLastModified());
    }

    private static void setUpdateParams(PreparedStatement update, Pack pack, int id) throws SQLException {
        update.setInt(1, pack.getNumber());
        update.setString(2, pack.getName());
        update.setString(3, pack.getSize());
        update.setLong(4, pack.getSizeKBits());
        update.setInt(5, pack.getEpisodeNumber());
        update.setTimestamp(6, pack.getLastModified());
        update.setInt(7, id);
    }

    private static boolean isChanged(Pack old, Pack pack) {
        return old.getNumber() != pack.getNumber()
                || !Objects.equals(old.getName(), pack.getName())
                || !Objects.equals(old.getSize(), pack.getSize())
                || !Objects.equals(old.getSizeKBits(), pack.getSizeKBits())
                || old.getEpisodeNumber() != pack.getEpisodeNumber();
    }

    public void addNewBot(AbstractDistroBot bot) {
        try (Connection connection = getConnection()) {
            try (PreparedStatement pstmt = connection.prepareStatement("INSERT INTO " + _BOTS_TABLE + "(name,url,type,status_id,last_seen,last_processed,informative) " + "Values (?,?,?,?,?,?,0);")) {
                pstmt.setString(1, bot.getName());
                pstmt.setString(2, bot.getURL());
                pstmt.setString(3, bot.getType());
                pstmt.setInt(4, bot.getStatusId());
                pstmt.setTimestamp(5, new Timestamp(System.currentTimeMillis()));
                pstmt.setTimestamp(6, new Timestamp(System.currentTimeMillis()));
                pstmt.executeUpdate();
            }

            try (PreparedStatement pstmt = connection.prepareStatement("SELECT * FROM " + _BOTS_TABLE + " WHERE name = '" + bot.getName() + "';")) {
                ResultSet rs = pstmt.executeQuery();
                if (rs.next()) {
                    bot.setId(rs.getInt(1));
                } else {
                    _myBot.getLogger().warn("Something went horribly wrong with addNewBot() for " + bot.getName());
                }
                rs.close();
            }
        } catch (SQLException e) {
            _myBot.getLogger().error("Error in UpdatePackListDAO.addNewBot() For: " + bot.getName() + " -- Duplicate Add... How?", e);
        }
    }

    public int updateBotURL(int id, String url) {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement("UPDATE " + _BOTS_TABLE + " SET url=? WHERE id=?;")) {
            pstmt.setString(1, url);
            pstmt.setInt(2, id);
            return pstmt.executeUpdate();
        } catch (SQLException e) {
            _myBot.getLogger().error("updateBotURL", e);
        }
        return 0;
    }

    public int updateBotType(int id, String type) {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement("UPDATE " + _BOTS_TABLE + " SET type=? WHERE id=?;")) {
            pstmt.setString(1, type.toUpperCase());
            pstmt.setInt(2, id);
            return pstmt.executeUpdate();
        } catch (SQLException e) {
            _myBot.getLogger().error("updateBotType", e);
        }
        return 0;
    }

    public int updateBotInformative(int id, int inform) {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement("UPDATE " + _BOTS_TABLE + " SET informative=? WHERE id=?;")) {
            pstmt.setInt(1, inform);
            pstmt.setInt(2, id);
            return pstmt.executeUpdate();
        } catch (SQLException e) {
            _myBot.getLogger().error("updateBotInformative", e);
        }
        return 0;
    }

    public int updateBotBatch(int id, int batch) {
        try (Connection connection = getConnection();
             PreparedStatement pstmt = connection.prepareStatement("UPDATE " + _BOTS_TABLE + " SET batchenable=? WHERE id=?;")) {
            pstmt.setInt(1, batch);
            pstmt.setInt(2, id);
            return pstmt.executeUpdate();
        } catch (SQLException e) {
            _myBot.getLogger().error("updateBotBatch", e);
        }
        return 0;
    }

    public void deleteBot(int botID) {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            stmt.executeUpdate("delete from " + _BOTS_TABLE + " where id = '" + botID + "'");
        } catch (SQLException e) {
            _myBot.getLogger().error("deleteBot", e);
        }
    }

    public void deleteBot(String botName) {
        try (Connection connection = getConnection();
             Statement stmt = connection.createStatement()) {
            stmt.executeUpdate("delete from " + _BOTS_TABLE + " where LOWER(`name`) = '" + botName.toLowerCase() + "'");
        } catch (SQLException e) {
            _myBot.getLogger().error("deleteBot", e);
        }
    }

    /**
     * Set offline bots in database and memory
     *
     * @param notBots
     */
    public void setOfflineBots(HashSet<String> offBots) {
        try {
            for (String botname : offBots) {
                UpdatePackListFunctions.getBot(botname).setStatusId(0);
            }
        } catch (Exception e) {
            _myBot.sendMessageFair("#" + _myBot.getProperty("admin_channel"), "setOfflineBots failed. Turning packlist module off.");
        }
    }

    /**
     * Set online bots in database and memory
     *
     * @param yesBots
     */
    public void setBotsOffline() {
        if (_updatePackList.getBotsMap().size() > 0) {
            StringBuilder sb = new StringBuilder();
            sb.append("UPDATE `updatepacklist_bots` set status_id = 0");
            try (Connection connection = getConnection();
                 Statement stmt = connection.createStatement()) {
                stmt.executeUpdate(sb.toString());
            } catch (SQLException e) {
                _myBot.getLogger().error("setOfflineBots Error: " + sb.toString(), e);
            }
        }
    }

    public void handelLatestPacks(MessageEvent message) {
        if (!_updatePackList.isIterating()) {
            UpdatePackListFunctions.getBot(message.getUser().getNick()).handleLatest();
        }
    }

    public void showLatestPacksForDCC(SendChat session, String args) throws IOException {
        LatestPacksDAO latestPacksDAO = new LatestPacksDAO(_myBot);
        int dispNumber = 10;
        if (!args.equals("")) {
            dispNumber = Integer.parseInt(args);
        }

        LinkedList<Pack> corn = latestPacksDAO.getLatestPacks(dispNumber);
        for (Pack pack : corn) {
            session.sendLine(Colors.BROWN + "[" + Colors.TEAL + pack.getSize() + Colors.BROWN + "] " + Colors.DARK_GRAY + pack.getName() + Colors.NORMAL + "  /MSG " + pack.getBotName() + " XDCC SEND " + pack.getNumber());
        }
    }

    public UpdatePackList getUpdatePackList() {
        return _updatePackList;
    }

    public void updateOwner(AbstractDistroBot bot, String botowner) throws SQLException {
        try (Connection connection = getConnection();
             PreparedStatement stmt = connection.prepareStatement("UPDATE updatepacklist_bots SET `owner` = ? WHERE id = ?")) {
            stmt.setString(1, botowner);
            stmt.setInt(2, bot.getId());
            stmt.executeUpdate();
            bot.setOwner(botowner);
        } catch (SQLException e) {
            _myBot.getLogger().error("SQL error in BotOwner updateOwner()", e);
        }
    }
}