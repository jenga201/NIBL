package com.nibl.bot.plugins.updatepacklist;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.sql.Timestamp;

import org.junit.jupiter.api.Test;

/** Pure POJO tests for {@link Pack}; no database or bot needed. */
class PackTest {

    private static final Timestamp MODIFIED = Timestamp.valueOf("2026-01-01 12:00:00");

    private static Pack pack(int botId, int number, String botName, String name, String size, long sizeKBits) {
        return new Pack(botId, number, botName, name, size, sizeKBits, -1, MODIFIED);
    }

    @Test
    void constructorPopulatesGetters() {
        Pack p = pack(3, 17, "FakeDistro", "Show - 17", "1.2G", 123L);
        assertEquals(3, p.getBotId());
        assertEquals(17, p.getNumber());
        assertEquals("FakeDistro", p.getBotName());
        assertEquals("Show - 17", p.getName());
        assertEquals("1.2G", p.getSize());
        assertEquals(123L, p.getSizeKBits());
        assertEquals(-1, p.getEpisodeNumber());
        assertEquals(MODIFIED, p.getLastModified());
    }

    @Test
    void settersUpdateEpisodeNumberAndLastModified() {
        Pack p = pack(3, 17, "FakeDistro", "Show - 17", "1.2G", 123L);
        p.setEpisodeNumber(17);
        Timestamp later = Timestamp.valueOf("2026-02-01 00:00:00");
        p.setLastModified(later);
        assertEquals(17, p.getEpisodeNumber());
        assertEquals(later, p.getLastModified());
    }

    @Test
    void compareToMatchesOnIdentityFields() {
        Pack a = pack(3, 17, "FakeDistro", "Show - 17", "1.2G", 123L);
        Pack b = pack(3, 17, "FakeDistro", "Show - 17", "1.2G", 999L);
        assertTrue(a.compareTo(b), "sizeKBits and lastModified are not part of compareTo");

        assertFalse(a.compareTo(pack(4, 17, "FakeDistro", "Show - 17", "1.2G", 123L)));
        assertFalse(a.compareTo(pack(3, 18, "FakeDistro", "Show - 17", "1.2G", 123L)));
        assertFalse(a.compareTo(pack(3, 17, "OtherBot", "Show - 17", "1.2G", 123L)));
        assertFalse(a.compareTo(pack(3, 17, "FakeDistro", "Show - 18", "1.2G", 123L)));
        assertFalse(a.compareTo(pack(3, 17, "FakeDistro", "Show - 17", "1.3G", 123L)));
    }

    @Test
    void toStringContainsKeyFields() {
        String s = pack(3, 17, "FakeDistro", "Show - 17", "1.2G", 123L).toString();
        assertTrue(s.contains("3"));
        assertTrue(s.contains("17"));
        assertTrue(s.contains("1.2G"));
        assertTrue(s.contains("Show - 17"));
    }
}
