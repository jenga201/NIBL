package com.nibl.bot.plugins.search;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.LinkedList;
import java.util.List;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;

import com.nibl.bot.testing.TestBot;

/**
 * Non-destructive tests for {@link SearchDAO}: pure episode/bracket parsing
 * plus webSearch against fake rows in the in-memory H2 database.
 */
class SearchDAOTest {

    private static TestBot bot;
    private static SearchDAO dao;

    @BeforeAll
    static void setUp() throws Exception {
        bot = new TestBot();
        dao = (SearchDAO) bot.getDAOFactory().getDAO("SearchDAO");
        assertNotNull(dao, "SearchDAO should be registered by the DAOFactory");

        int botId = bot.insertBot("FakeDistro", "http://example.invalid/list.txt", "HTTP", "Tester", 1, 1);
        bot.insertPack(botId, 101, "Test Show - 01 [1080p]", "1.2G", 1288490188L, 1, "2026-01-02 00:00:00");
        bot.insertPack(botId, 102, "Another Show - 02", "700M", 734003200L, 2, "2026-01-01 00:00:00");
        bot.insertPack(botId, 103, "Unrelated Movie", "4.4G", 4724464025L, -1, "2026-01-03 00:00:00");
    }

    @Test
    void findEpisodeNumberUnderscore() {
        List<Integer> episodes = dao.findEpisodeNumber("Show_01");
        assertEquals(List.of(1), episodes);
    }

    @Test
    void findEpisodeNumberSpacesBecomeUnderscores() {
        List<Integer> episodes = dao.findEpisodeNumber("Show - 05");
        assertEquals(List.of(5), episodes);
    }

    @Test
    void findEpisodeNumberRange() {
        List<Integer> episodes = dao.findEpisodeNumber("Show_01-12");
        assertEquals(List.of(1, 12), episodes);
    }

    @Test
    void findEpisodeNumberVersionSuffix() {
        List<Integer> episodes = dao.findEpisodeNumber("Show_05v2");
        assertEquals(List.of(5), episodes);
    }

    @Test
    void findEpisodeNumberNoMatch() {
        assertTrue(dao.findEpisodeNumber("NoEpisodeHere").isEmpty());
    }

    @Test
    void removeBracketsStripsSquareAndRoundBrackets() {
        assertEquals("Show", dao.removeBrackets("[Group] Show (BD) [1080p]").trim());
    }

    @Test
        void removeBracketsUnclosedBracketSwallowsTail() {
            assertEquals("Show ", dao.removeBrackets("Show [1080p"));
        }
    }
