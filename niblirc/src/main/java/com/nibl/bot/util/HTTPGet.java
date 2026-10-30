package com.nibl.bot.util;

import java.io.IOException;
import java.io.InputStream;
import java.io.RandomAccessFile;
import java.net.URI;
import java.net.URLConnection;
import java.security.cert.X509Certificate;

import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;

import com.nibl.bot.Bot;
import com.nibl.bot.BotExtend;

public class HTTPGet extends BotExtend {

	public HTTPGet(Bot myBot) {
		super(myBot);
		// Create a trust manager that does not validate certificate chains
		final TrustManager[] trustAllCerts = new TrustManager[] { new X509TrustManager() {

			@Override
			public void checkServerTrusted(X509Certificate[] chain, String authType) {
			}

			@Override
			public void checkClientTrusted(X509Certificate[] chain, String authType) {
			}

			@Override
			public X509Certificate[] getAcceptedIssuers() {
				return null;
			}
		} };

		HostnameVerifier hv = (hostname, session) -> true;
		HttpsURLConnection.setDefaultHostnameVerifier(hv);

		try {
			// Install the all-trusting trust manager
			final SSLContext sslContext = SSLContext.getInstance("TLS");
			sslContext.init(null, trustAllCerts, new java.security.SecureRandom());
			HttpsURLConnection.setDefaultSSLSocketFactory(sslContext.getSocketFactory());
		} catch (Exception e) {
			e.printStackTrace();
		}
	}

	public RandomAccessFile getURLData(String url) {
		try {
			URLConnection urlConnection = URI.create(url).toURL().openConnection();
			urlConnection.setConnectTimeout(10000);
			urlConnection.setReadTimeout(10000);
			urlConnection.setRequestProperty("User-Agent", "ooinuzaBot");
			InputStream inputStream = urlConnection.getInputStream();
			return InputStreamConverter.toRandomAccessFile(inputStream);
		} catch (IOException e) {
			e.printStackTrace();
		}

		return null;
	}

}
