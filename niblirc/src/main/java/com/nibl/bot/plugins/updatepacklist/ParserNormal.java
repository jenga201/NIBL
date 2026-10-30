package com.nibl.bot.plugins.updatepacklist;

import java.io.RandomAccessFile;
import java.util.LinkedList;
import java.util.List;

import com.nibl.bot.util.HTTPGet;

public class ParserNormal extends AbstractParser {

	public LinkedList<Pack> parse(AbstractDistroBot bot, RandomAccessFile in) throws Exception {

		PackParseFunctions packParseFunctions = new PackParseFunctions(bot.getPircBotX());

		LinkedList<Pack> output = new LinkedList<Pack>();

		if (bot == null || in == null) {
			if (bot != null) {
				bot.getPircBotX().getLogger().error("Bad buffer or bot, cannot parse packlist");
			}
			return output;
		}

		in.seek(0);

		String line;
		while ((line = PackParseFunctions.readUtf8Line(in)) != null) {
			if (line.indexOf("<br>") >= 0) {
				// Some servers deliver the whole listing on one line separated by <br>.
				for (String part : line.split("<br>")) {
					addPack(packParseFunctions, bot, output, part);
				}
			} else {
				addPack(packParseFunctions, bot, output, line);
			}
		}

		return output;
	}

	private void addPack(PackParseFunctions packParseFunctions, AbstractDistroBot bot, LinkedList<Pack> output, String line) {
		line = line.replace("\u0002", "");

		if (!line.trim().startsWith("#")) {
			return;
		}

		try {
			int beginIndex = line.indexOf('#') + 1;
			int endIndex = line.indexOf(' ', beginIndex);
			int number = Integer.parseInt(line.substring(beginIndex, endIndex));

			beginIndex = line.indexOf('[', endIndex) + 1;
			endIndex = line.indexOf(']', beginIndex);
			String size = line.substring(beginIndex, endIndex);

			String name = line.substring(endIndex + 2);

			output.add(packParseFunctions.buildPack(bot, number, name, size));
		} catch (Exception e) {
			// Ignore lines that can't be parsed (junk, headers, malformed sizes).
		}
	}

	public static void main(String[] args) throws Exception {
		HttpDistroBot tempBot = new HttpDistroBot(null, 1, "tempBot", "https://gin.sadaharu.eu/Gin.txt", "HTTP", 1, null, null, null, 0, 0, "Jenga", 0);
		HTTPGet httpget = new HTTPGet(null);
		RandomAccessFile in = httpget.getURLData(tempBot.getURL());

		ParserNormal parser = new ParserNormal();
		List<Pack> packs = parser.parse(tempBot, in);
		for (Pack pack : packs) {
			System.out.println(pack.toString());
		}
	}
}