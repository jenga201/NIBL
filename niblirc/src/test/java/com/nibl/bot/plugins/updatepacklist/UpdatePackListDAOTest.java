package com.nibl.bot.plugins.updatepacklist;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertInstanceOf;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.sql.Timestamp;
import java.util.HashSet;
import java.util.LinkedList;
import java.util.List;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;

import com.nibl.bot.testing.TestBot;

/**
 * Non-destructive round-trip tests for {@link UpdatePackListDAO} against the
 * in-memory H2 database: fake bot rows are seeded, then read back, re-listed
 * and status-flipped through the DAO's own SQL.
 */
class UpdatePackListDAOTest {

    private static TestBot bot;
    private static UpdatePackListDAO dao;
    private static int fakeBotId;

    @BeforeAll
    static void setUp() throws Exception {
        bot = new TestBot();
        dao = (UpdatePackListDAO) bot.getDAOFactory().getDAO("UpdatePackListDAO");
        assertNotNull(dao, "UpdatePackListDAO should be registered by the DAOFactory");

        fakeBotId = bot.insertBot("FakeDistro", "http://example.invalid/list.txt", "HTTP", "Tester", 1, 1);
        bot.insertBot("NoticeBot", "NONE", "NOTICE", "Tester", 1, 0);
    }

    private static Pack pack(int number, String name, String size, long sizeKBits, int episodeNumber) {
        return new Pack(fakeBotId, number, "FakeDistro", name, size, sizeKBits, episodeNumber,
                new Timestamp(System.currentTimeMillis()));
    }

    @Test
    void getBotFromDatabaseIsCaseInsensitive() {
        AbstractDistroBot distroBot = dao.getBotFromDatabase("fakedistro");
        assertNotNull(distroBot);
        assertInstanceOf(HttpDistroBot.class, distroBot);
        assertEquals(fakeBotId, distroBot.getId());
        assertEquals("FakeDistro", distroBot.getName());
        assertEquals("http://example.invalid/list.txt", distroBot.getURL());
        assertEquals("Tester", distroBot.getOwner());
        assertEquals(1, distroBot.getStatusId());
        assertEquals(1, distroBot.getParserId());
    }

    @Test
    void getBotFromDatabaseUnknownNameReturnsNull() {
        assertNull(dao.getBotFromDatabase("no-such-bot"));
    }

    @Test
    void getNoticeBotNamesOnlyReturnsNoticeType() {
        HashSet<String> names = dao.getNoticeBotNames();
        assertTrue(names.contains("NoticeBot"));
        assertFalse(names.contains("FakeDistro"));
    }

    @Test
    void updateListingPersistsPacksAndReloadsThem() throws SQLException {
        AbstractDistroBot distroBot = dao.getBotFromDatabase("FakeDistro");
        List<Pack> listing = new LinkedList<>();
        listing.add(pack(1, "Show One - 01", "1.2G", 1288490188L, 1));
        listing.add(pack(2, "Bob's Show - 02", "700M", 734003200L, 2));
        listing.add(pack(3, "Show Three - 03", "9.1M", 9542041L, 3));
        distroBot.setListing(listing);

        dao.updateListing(distroBot);

        List<Pack> fromDb = dao.getBotPackListFromDatabase(distroBot);
        assertEquals(3, fromDb.size());

        Pack reloaded = fromDb.stream().filter(p -> p.getNumber() == 2).findFirst().orElseThrow();
        assertEquals("Bob's Show - 02", reloaded.getName(), "apostrophes must survive the SQL escaping");
        assertEquals("700M", reloaded.getSize());
        assertEquals(734003200L, reloaded.getSizeKBits());
        assertEquals(2, reloaded.getEpisodeNumber());
        assertEquals("FakeDistro", reloaded.getBotName());
    }

    @Test
    void updateListingReplacesPreviousListing() throws SQLException {
        AbstractDistroBot distroBot = dao.getBotFromDatabase("FakeDistro");
        List<Pack> listing = new LinkedList<>();
        listing.add(pack(10, "Renamed Show - 10", "2.5G", 2684354560L, 10));
        listing.add(pack(11, "Brand New Show - 11", "300M", 314572800L, 11));
        distroBot.setListing(listing);

        dao.updateListing(distroBot);

        List<Pack> fromDb = dao.getBotPackListFromDatabase(distroBot);
        assertEquals(2, fromDb.size(), "old packs must be deleted before the new listing is inserted");
        assertTrue(fromDb.stream().noneMatch(p -> p.getNumber() == 1));
        assertTrue(fromDb.stream().anyMatch(p -> p.getNumber() == 10 && p.getName().equals("Renamed Show - 10")));
    }

    @Test
    void setStatusIdPersistsOfflineStatus() throws SQLException {
        AbstractDistroBot distroBot = dao.getBotFromDatabase("FakeDistro");
        distroBot.setStatusId(0);

        AbstractDistroBot reloaded = dao.getBotFromDatabase("FakeDistro");
        assertEquals(0, reloaded.getStatusId());

        distroBot.setStatusId(1);
        assertEquals(1, dao.getBotFromDatabase("FakeDistro").getStatusId());
    }

    @Test
    void setBotParserPersistsParserId() throws SQLException {
        AbstractDistroBot distroBot = dao.getBotFromDatabase("FakeDistro");
        distroBot.setParserId(4);
        assertEquals(4, dao.getBotFromDatabase("FakeDistro").getParserId());

        distroBot.setParserId(1);
        assertEquals(1, dao.getBotFromDatabase("FakeDistro").getParserId());
    }

    @Test
    void statusLookupTableIsSeeded() throws SQLException {
        Statement stmt = bot.getConnection().createStatement();
        ResultSet rs = stmt.executeQuery("SELECT description FROM updatepacklist_status WHERE id = 1");
        assertTrue(rs.next());
        assertEquals("ONLINE", rs.getString(1));
        rs.close();
        stmt.close();
    }
}
