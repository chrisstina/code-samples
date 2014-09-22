package com.herocraft.logcollector.tailer;

/**
 * Слушатель событий класса FileTailer
 * @author chriss
 *
 */
public interface FileTailerListener {
	public void newLogFileLine(String line); 
}