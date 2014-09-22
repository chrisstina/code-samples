package com.herocraft.logcollector.producer;

import java.util.regex.Pattern;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;
import com.herocraft.logcollector.tailer.FileTailerListener;

@Service("LogService")
public class LogService implements FileTailerListener {
	
	@Autowired
	protected LogSender logSender;
	
	protected String filter = ".*notice.*";
	protected Pattern errorPattern;
	
	public LogService()
	{
		errorPattern = Pattern.compile(filter);
	}
	
	public void newLogFileLine(String line) {
		if (errorPattern.matcher(line).matches())
			 logSender.newLogFileLine(line);
	}
}