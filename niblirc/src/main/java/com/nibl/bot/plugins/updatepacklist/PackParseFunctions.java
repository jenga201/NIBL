package com.nibl.bot.plugins.updatepacklist;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.nio.charset.StandardCharsets;
import java.sql.Timestamp;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.Objects;

import com.nibl.bot.Bot;
import com.nibl.bot.BotExtend;
import com.nibl.bot.plugins.search.SearchDAO;

public class PackParseFunctions extends BotExtend {

    public enum SizeMult {
        SIZE_NULL("", 0d),
        SIZE_KB("K", 1d),
        SIZE_MB("M", Math.pow(1024, 2)),
        SIZE_GB("G", Math.pow(1024, 3)),
        SIZE_TB("T", Math.pow(1024, 4)),
        SIZE_PB("P", Math.pow(1024, 5));

        String text;
        Double mul;

        SizeMult(String text, Double mul) {
            this.text = text;
            this.mul = mul;
        }

        public SizeMult getSizeMultByText(String text) {
            for (SizeMult sizeMult : SizeMult.values()) {
                if (sizeMult.text.equalsIgnoreCase(text)) {
                    return sizeMult;
                }
            }
            return SizeMult.SIZE_NULL;
        }
    }

    ;

    public enum Parser {
        PARSER_NULL(0, null),
        PARSER_NORMAL(PARSER_NORMAL_ID, new ParserNormal()),
        PARSER_DINOEX(PARSER_DINOEX_ID, new ParserDinoex()),
        PARSER_JS(PARSER_JS_ID, new ParserJS()),
        PARSER_TABS(PARSER_TABS_ID, new ParserTabs());

        Integer _parserId;
        AbstractParser _parser;

        Parser(Integer parserId, AbstractParser parser) {
            _parserId = parserId;
            _parser = parser;
        }

        public Parser getParserById(Integer Id) {
            for (Parser parser : Parser.values()) {
                if (parser._parserId == Id) {
                    return parser;
                }
            }
            return Parser.PARSER_NULL;
        }
    }

    ;

    public static final int PARSER_NULL_ID = 0;
    public static final int PARSER_NORMAL_ID = 1;
    public static final int PARSER_DINOEX_ID = 2;
    public static final int PARSER_JS_ID = 3;
    public static final int PARSER_TABS_ID = 4;

    /**
     * Reads a line the same way {@link RandomAccessFile#readLine()} does, but
     * decodes the bytes as UTF-8 instead of ISO-8859-1. Packlists are UTF-8,
     * so the stock readLine corrupts every non-ASCII filename.
     */
    public static String readUtf8Line(RandomAccessFile in) throws IOException {
        ByteArrayOutputStream lineBytes = new ByteArrayOutputStream();
        int b = in.read();
        while (b != -1 && b != '\n') {
            if (b != '\r') {
                lineBytes.write(b);
                b = in.read();
                continue;
            }
            long pos = in.getFilePointer();
            if (in.read() != '\n') {
                in.seek(pos);
            }
            break;
        }
        return (b == -1 && lineBytes.size() == 0) ? null : lineBytes.toString(StandardCharsets.UTF_8);
    }

    public SearchDAO _searchDAO;

    public PackParseFunctions(Bot myBot) {
        super(myBot);
        _searchDAO = (SearchDAO) _myBot.getDAOFactory().getDAO("SearchDAO");
    }

    public Pack buildPack(AbstractDistroBot bot, int packnumber, String packname, String packsize) {

        Long sizeKBits = 0L;
        try {
            packsize = packsize.trim();
            String sizeDesc = packsize.substring(packsize.length() - 1);
            SizeMult sizeMult = SizeMult.SIZE_NULL.getSizeMultByText(sizeDesc);
            sizeKBits = (long) (Double.parseDouble(packsize.substring(0, packsize.length() - 1).replace("<", "")) * sizeMult.mul);
        } catch (Exception e) {
            _myBot.getLogger().warn("Failed to calculate size, defaulting to 0 kbits: " + packsize);
        }

        return new Pack(bot.getId(), packnumber, bot.getName(), packname, packsize, sizeKBits, -1, new Timestamp(System.currentTimeMillis()));
    }

    /*
     * It is sometimes unknown which parser to use.
     * If no parser is known, each parser will be tried until there is a success.
     * If the parser fails (future packlist changes), all parsers will be re-tried
     */
    public LinkedList<Pack> randomAccessFileToPacks(AbstractDistroBot bot, RandomAccessFile in)
            throws Exception {

        LinkedList<Pack> output = new LinkedList<Pack>();
        try {
            output = Parser.PARSER_NULL.getParserById(bot.getParserId())._parser.parse(bot, in);
            if (output.size() == 0) {
                _myBot.getLogger().warn("Not able to find packs using declared parser. Attempting others...");
            } else {
                return output;
            }
        } catch (Exception e) {
            // New bots with parser unknown will throw an error here. Ignore it and allow testing of other parsers.
        }

        for (Parser parser : Parser.values()) {
            if (parser._parserId == Parser.PARSER_NULL._parserId ||
                    parser._parserId == bot.getParserId()) {
                continue;
            }
            output = parser._parser.parse(bot, in);
            if (output.size() > 0) {
                _myBot.getLogger().info("Found packs using parser " + parser._parserId + ".");
                bot.setParserId(parser._parserId);
                return output;
            }
        }
        _myBot.getLogger().error("No packs found after trying all parsers");
        return output;
    }

    public Boolean packListChanged(AbstractDistroBot bot, List<Pack> newData) {

        _myBot.getLogger().trace("Start Compare and update database");
        Boolean update = false;
        //packs have been removed or added
        if (bot.getListing().size() != newData.size()) {
            update = true;
        }

        // Match by stable file identity (name) instead of the XDCC pack number,
        // so a bot that renumbers/reorders its list only rewrites the shifted
        // numbers rather than treating every affected file as brand new.
        Map<String, Pack> byName = new HashMap<String, Pack>();
        for (Pack pack : bot.getListing()) {
            byName.put(packName(pack), pack);
        }

        for (int i = 0; i < newData.size(); i++) { // For each piece of new data
            Pack newPack = newData.get(i);
            Pack oldPack = byName.get(packName(newPack));

            boolean sameContent = oldPack != null
                    && Objects.equals(oldPack.getSize(), newPack.getSize())
                    && Objects.equals(oldPack.getSizeKBits(), newPack.getSizeKBits());

            if (sameContent) {
                // Same file: preserve its stable timestamp and derived episode
                // number. A shifted XDCC number still needs persisting.
                newPack.setLastModified(oldPack.getLastModified());
                newPack.setEpisodeNumber(oldPack.getEpisodeNumber());
                if (oldPack.getNumber() != newPack.getNumber()) {
                    update = true;
                }
            } else {
                // New file, or the same name with a different size: persist it.
                update = true;

                // Attempt to get episodeNumber
                int episodeNumber = -1;
                LinkedList<Integer> episodes = _searchDAO.findEpisodeNumber(_searchDAO.removeBrackets(newPack.getName()));
                if (!episodes.isEmpty()) {
                    episodeNumber = episodes.pop();
                }

                newPack.setEpisodeNumber(episodeNumber);
            }
        }

        return update;

    }

    /**
     * Null-safe filename key; matches {@link UpdatePackListDAO#packKey}.
     */
    private static String packName(Pack pack) {
        String name = pack.getName();
        return name == null ? "" : name;
    }

}
