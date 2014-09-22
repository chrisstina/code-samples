package com.herocraft.logcollector;

import org.springframework.context.ApplicationContext;
import org.springframework.context.support.ClassPathXmlApplicationContext;

import com.herocraft.logcollector.producer.LogService;
import com.herocraft.logcollector.tailer.FileTailerService;

/**
 * Демон, читающий логи из заданного файла и отправляющий
 * сообщения определенного формата в message queue.
 * 
 * @author chriss
 */
public class LogcollectorService {
	
	public static void main(String[] args) {
		
		ApplicationContext ctx = new ClassPathXmlApplicationContext("/META-INF/spring/app-context.xml");
    	LogService logSrv = (LogService) ctx.getBean("LogService");

    	if (args.length == 0) {
    		System.err.println ("Не задано имя файла логов!");
			System.exit(0);
    	} else {
    		FileTailerService reader = new FileTailerService(args[0]);
        	reader.addLogFileTailerListener(logSrv);
    		reader.start();
    	}
	}
}
