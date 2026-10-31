package com.nibl.bot.util;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.ArrayList;
import java.util.List;

/**
 * Minimal Meilisearch client for the few read endpoints niblirc needs. Uses the
 * JDK's built-in HttpClient (Java 21) and Jackson for the JSON payload.
 */
public class MeiliSearchClient {

    public record SearchHit(String name, String size, int number, String botName) {}

    private static final ObjectMapper JSON = new ObjectMapper();

    private final String host;
    private final String masterKey;
    private final HttpClient http;

    public MeiliSearchClient(String host, String masterKey) {
            String normalized = (host == null || host.isBlank()) ? "http://meilisearch:7700" : host.trim();
            while (normalized.endsWith("/")) {
                normalized = normalized.substring(0, normalized.length() - 1);
            }
            this.host = normalized;
            this.masterKey = masterKey == null ? "" : masterKey;
            this.http = HttpClient.newBuilder()
                    .connectTimeout(Duration.ofSeconds(5))
                    .build();
        }

    /**
     * Fuzzy, typo-tolerant search over active bots' packs, sorted by highest
     * pack number first (the website's default ordering).
     */
    public List<SearchHit> searchPacks(String query, int limit) throws Exception {
        String body = "{\"q\":\"" + escape(query)
                + "\",\"filter\":\"bot_active = true\""
                + ",\"sort\":[\"number:desc\"]"
                + ",\"limit\":" + limit
                + ",\"matchingStrategy\":\"all\"}";

        HttpRequest.Builder request = HttpRequest.newBuilder()
                .uri(URI.create(host + "/indexes/packs/search"))
                .timeout(Duration.ofSeconds(10))
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(body));
        if (!masterKey.isEmpty()) {
            request.header("Authorization", "Bearer " + masterKey);
        }

        HttpResponse<String> response = http.send(request.build(), HttpResponse.BodyHandlers.ofString());
        if (response.statusCode() != 200) {
            throw new IllegalStateException("Meilisearch search returned HTTP " + response.statusCode());
        }

        JsonNode root = JSON.readTree(response.body());
        List<SearchHit> hits = new ArrayList<>();
        for (JsonNode hit : root.path("hits")) {
            hits.add(new SearchHit(
                    hit.path("name").asText(),
                    hit.path("size").asText(),
                    hit.path("number").asInt(),
                    hit.path("bot_name").asText()));
        }
        return hits;
    }

    private static String escape(String value) {
        return value.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}