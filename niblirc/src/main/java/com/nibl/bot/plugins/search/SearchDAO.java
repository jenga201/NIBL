package com.nibl.bot.plugins.search;

import java.util.LinkedList;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

import org.pircbotx.Colors;

import com.nibl.bot.Bot;
import com.nibl.bot.dao.DataAccessObject;
import com.nibl.bot.util.MeiliSearchClient;
import com.nibl.bot.util.MeiliSearchClient.SearchHit;

public class SearchDAO extends DataAccessObject {

    private static final int LIMIT = 15;

    Pattern epPattern = Pattern.compile(".*_(?:ep|episode)?(\\d{1,3})(?:v\\d+)?(?:-(?:ep|episode)?(\\d{1,3})(?:v\\d+)?)?(?:_|$)", Pattern.CASE_INSENSITIVE);
    Pattern dotPattern = Pattern.compile("((?<!\\d)\\.|\\.(?!\\d)| )");

    private MeiliSearchClient _meili;

    public SearchDAO(Bot myBot) {
        super(myBot);
    }

    @Override
    public void createTables() {
    }

    @Override
    public void dropTables() {
    }

    /**
     * Search packs via the Meilisearch read model: fuzzy/typo-tolerant, active
     * (online + non-informative) bots only, sorted by highest pack number first.
     *
     * @return rows of {@code {sender, pack output line}}
     */
    public LinkedList<String[]> search(String sender, String searchTerm) {
        String term = searchTerm == null ? "" : searchTerm.trim();
        LinkedList<String[]> output = new LinkedList<String[]>();

        if (term.isEmpty()) {
            return output;
        }

        String httpList = "https://nibl.co.uk/search?query=" + term.replace(" ", "+");

        try {
            List<SearchHit> hits = client().searchPacks(term, LIMIT);
            if (hits.isEmpty()) {
                output.add(new String[]{sender, Colors.RED + "No Results Found."});
                return output;
            }

            output.addFirst(new String[]{
                    sender,
                    Colors.DARK_GRAY + "Sending Results... To stop results " + Colors.TEAL
                            + "/MSG " + _myBot.getBot().getNick() + " STOP " + Colors.DARK_GRAY
                            + "- Full Listing at: " + Colors.TEAL + httpList
            });

            for (SearchHit hit : hits) {
                output.add(new String[]{
                        sender,
                        Colors.BROWN + "[" + Colors.TEAL + hit.size() + Colors.BROWN + "] "
                                + Colors.DARK_GRAY + hit.name() + Colors.NORMAL
                                + "  /MSG " + hit.botName() + " XDCC SEND " + hit.number()
                });
            }
        } catch (Exception e) {
            _myBot.getLogger().error("Meilisearch search failed", e);
            output.add(new String[]{sender, Colors.RED + "Search failed. Please try again."});
        }

        return output;
    }

    private MeiliSearchClient client() {
        if (_meili == null) {
            _meili = new MeiliSearchClient(
                    _myBot.getProperty("meili_host"),
                    _myBot.getProperty("meili_master_key"));
        }
        return _meili;
    }

    public LinkedList<Integer> findEpisodeNumber(String episode) {
        Matcher matcher = dotPattern.matcher(episode);
        episode = matcher.replaceAll("_");
        LinkedList<Integer> output = new LinkedList<Integer>();

        Matcher epMatcher = epPattern.matcher(episode);
        if (epMatcher.find()) {
            try {
                output.add(Integer.parseInt(removeLeadingZeros(epMatcher.group(1))));
            } catch (Exception e) {
            }
            try {
                output.add(Integer.parseInt(removeLeadingZeros(epMatcher.group(2))));
            } catch (Exception e) {
            }
        }

        return output;
    }

    /**
     * This method removes the Leading '0's of a string
     */
    private String removeLeadingZeros(String input) {
        while (input.startsWith("0"))
            input = input.replaceFirst("0", "");
        return input;
    }

    /**
     * This method removes all Brackets [ ] and ( )
     */
    public String removeBrackets(String input) {
        StringBuilder strippedSearchTerm = new StringBuilder(input.length());
        int open = 0;
        for (char t : input.toCharArray()) {
            if (t == '[' || t == '(') {
                open++;
            }
            if (open < 1) {
                strippedSearchTerm.append(t);
            }
            if (t == ']' || t == ')') {
                open--;
            }
        }
        return strippedSearchTerm.toString();
    }
}