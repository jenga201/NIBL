package com.nibl.bot.util;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.io.RandomAccessFile;
import java.net.ServerSocket;
import java.net.Socket;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import com.nibl.bot.testing.TestBot;

/**
 * Non-destructive tests for {@link HTTPGet}: configurable connect/read
 * timeout parsing and the read timeout actually being applied to a remote
 * fetch.
 */
class HTTPGetTest {

    private TestBot bot;
    private HTTPGet httpGet;

    @BeforeEach
    void setUp() throws Exception {
        bot = new TestBot();
        httpGet = new HTTPGet(bot);
    }

    @Test
    void parseTimeoutFallsBackWhenMissing() {
        assertEquals(15000, httpGet.parseTimeout("http_connect_timeout", 15000));
        assertEquals(60000, httpGet.parseTimeout("http_read_timeout", 60000));
    }

    @Test
    void parseTimeoutReadsConfiguredValue() {
        bot.setProperty("http_connect_timeout", "20000");
        bot.setProperty("http_read_timeout", "45000");
        assertEquals(20000, httpGet.parseTimeout("http_connect_timeout", 15000));
        assertEquals(45000, httpGet.parseTimeout("http_read_timeout", 60000));
    }

    @Test
    void parseTimeoutFallsBackOnInvalidOrBlank() {
        bot.setProperty("http_connect_timeout", "not-a-number");
        assertEquals(15000, httpGet.parseTimeout("http_connect_timeout", 15000));
        bot.setProperty("http_read_timeout", "   ");
        assertEquals(60000, httpGet.parseTimeout("http_read_timeout", 60000));
    }

    @Test
    void readTimeoutIsAppliedToRemoteFetch() throws Exception {
        try (ServerSocket server = new ServerSocket(0)) {
            Thread holder = new Thread(() -> {
                try (Socket socket = server.accept()) {
                    // Accept the connection but never send an HTTP response.
                    Thread.sleep(5000);
                } catch (Exception ignored) {
                    // The client closes the connection after timing out.
                }
            });
            holder.setDaemon(true);
            holder.start();

            bot.setProperty("http_connect_timeout", "1000");
            bot.setProperty("http_read_timeout", "500");

            long start = System.currentTimeMillis();
            RandomAccessFile result = httpGet.getURLData("http://localhost:" + server.getLocalPort() + "/");
            long elapsed = System.currentTimeMillis() - start;

            assertNull(result, "a silent server should cause a read timeout and return null");
            assertTrue(elapsed < 3000, "should stop at the 500ms read timeout, took " + elapsed + "ms");
        }
    }
}