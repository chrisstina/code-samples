package com.herocraft.logcollector.tailer;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.util.HashSet;
import java.util.Iterator;
import java.util.Set;

/**
 * Класс для чтения файлов, изменяемых во время работы скрипта (tail).
 * 
 * Предназначен для чтения логов.
 * Отслеживает добавление новых строк в файл после его открытия,
 * вызывает событие newLogFileLine для каждого подписанного слушателя.
 * Также обрабатывает случаи ротации файлов.
 * 
 * @author chriss
 *
 */
public class FileTailerService extends Thread {
	  /**
	   * Экземпляр файла для чтения
	   */
	  protected File logfile;
	  
	  /**
	   * Слушатели события fireNewLogFileLine
	   */
	  protected Set<FileTailerListener> listeners = new HashSet<FileTailerListener>();
	  
	  /**
	   * Частота проверки изменений в файле в секундах
	   */
	  private long sampleInterval = 5000;
	  
	  /**
	   * Определяет, начинать ли чтение с начала файла, или продолжать с конца
	   */
	  private boolean startAtBeginning = false;
	 
	  /**
	   * Файл читается в данный момент
	   */
	  private boolean tailing = false;
	  
	  public FileTailerService(String source) {
		  logfile = new File(source);
	  }
	  
	  public void stopTailing() {
		  this.tailing = false;
	  }
	  
	  public void addLogFileTailerListener( FileTailerListener l ) {
	    this.listeners.add( l );
	  }

	  public void removeLogFileTailerListener( FileTailerListener l ) {
	    this.listeners.remove( l );
	  }

	  protected void fireNewLogFileLine( String line ) {
	    for( Iterator<FileTailerListener> i = listeners.iterator(); i.hasNext(); ) {
	    	FileTailerListener l = ( FileTailerListener )i.next();
	    	l.newLogFileLine( line );
	    }
	  }
	  
	  @Override
	  public void run() {
		    long filePointer = 0;

		    if(this.startAtBeginning)
		      filePointer = 0;
		    else
		      filePointer = logfile.length();
		    
		    try {
		    	this.tailing = true;
				RandomAccessFile raFile = new RandomAccessFile(logfile, "r");
				while(this.tailing) {
					long fileLength = logfile.length();
					
					if (fileLength < filePointer) {
						// В случае ротации или удаления файла, 
						// открыть файл заново и обновить filePointer
						raFile = new RandomAccessFile( logfile, "r" );
			            filePointer = 0;
					}
					
					if (fileLength > filePointer) {
						raFile = new RandomAccessFile(logfile, "r");
						raFile.seek(filePointer);
			            String line = raFile.readLine();
						while( line != null ) {
						// вызываем событие чтения новой строки
			              fireNewLogFileLine(line);
			              line = raFile.readLine();
			            }
						
			            filePointer = raFile.getFilePointer();
					}
					sleep(sampleInterval);
				}
				
				raFile.close();
				
			} catch (FileNotFoundException e) {
				System.err.println ("Файл логов не найден!");
			} catch (IOException e) {
				e.printStackTrace();
			} catch (InterruptedException e) {
				e.printStackTrace();
			}
	  }
}
