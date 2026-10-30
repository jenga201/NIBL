package com.nibl.bot.testing;

import java.io.IOException;
import java.io.InputStream;
import java.io.RandomAccessFile;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;

/** Helpers for turning classpath fixtures into the file handles the parsers expect. */
public final class TestFiles {

    private TestFiles() {
    }

    /** Copies a classpath resource into tempDir and opens it as a RandomAccessFile. */
    public static RandomAccessFile fixtureAsRandomAccessFile(String resource, Path tempDir) throws IOException {
        Path target = tempDir.resolve(resource.substring(resource.lastIndexOf('/') + 1));
        try (InputStream in = TestFiles.class.getResourceAsStream(resource)) {
            if (in == null) {
                throw new IOException("Fixture not found on the test classpath: " + resource);
            }
            Files.copy(in, target, StandardCopyOption.REPLACE_EXISTING);
        }
        return new RandomAccessFile(target.toFile(), "r");
    }
}
